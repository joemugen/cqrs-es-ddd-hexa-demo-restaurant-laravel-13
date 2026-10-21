<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * @return list<StoredEvent>
 */
function loggedOfType(string $type): array
{
    return array_values(array_filter(
        app(EventStore::class)->readAllFrom(0),
        fn (StoredEvent $event): bool => $event->type->is($type),
    ));
}

function runWorkers(string ...$contexts): void
{
    foreach ($contexts as $context) {
        test()->artisan("{$context}:consume", ['--stop-when-empty' => true])->doesntExpectOutputToContain('failed')->assertSuccessful();
    }
}

function theKitchenAndTheDiningRoomCatchUp(): void
{
    runWorkers('front-of-house', 'kitchen', 'front-of-house', 'kitchen', 'front-of-house');
}

function theFulfilmentOf(string $id): array
{
    return test()->getJson("/api/front-of-house/orders/{$id}")->assertOk()->json();
}

/**
 * @return list<string>
 */
function dishesOnTheScreenFor(string $id): array
{
    $ticket = collect(test()->getJson('/api/kitchen/screen')->json())->firstWhere('ticketId', $id);

    return $ticket === null ? [] : array_column($ticket['lines'], 'dishName');
}

function reviseWith(string $id, string ...$menuItemIds): TestResponse
{
    return test()->postJson("/api/front-of-house/orders/{$id}/revisions", ['lines' => array_map(
        fn (string $menuItemId): array => ['menuItemId' => $menuItemId, 'quantity' => 1, 'course' => 'dessert'],
        $menuItemIds,
    )]);
}

describe('the order fulfilment saga', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-7/guests', ['guestCount' => 2])->assertOk();
        $this->steak = ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'];
        $this->salad = ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'];
    });

    describe('when the kitchen opens the ticket', function () {
        test('is confirmed, at once in sync mode', function () {
            $id = $this->postJson('/api/front-of-house/tables/table-7/orders', ['lines' => [$this->salad]])->json('tableOrderId');

            $this->getJson("/api/front-of-house/orders/{$id}")
                ->assertOk()
                ->assertExactJson(['tableOrderId' => $id, 'tableId' => 'table-7', 'status' => 'confirmed', 'reason' => null, 'unavailableLines' => []]);
        });

        test('goes from awaiting the kitchen to confirmed as the workers catch up', function () {
            config(['consumption.mode' => 'worker']);
            $id = $this->postJson('/api/front-of-house/tables/table-7/orders', ['lines' => [$this->salad]])->json('tableOrderId');

            $this->getJson("/api/front-of-house/orders/{$id}")->assertNotFound();

            runWorkers('front-of-house');
            $this->getJson("/api/front-of-house/orders/{$id}")->assertOk()->assertJsonPath('status', 'awaiting-kitchen');

            runWorkers('kitchen', 'front-of-house');
            $this->getJson("/api/front-of-house/orders/{$id}")->assertOk()->assertJsonPath('status', 'confirmed');
        });

        test('a dish declared 86 after the order was placed does not make it partial', function () {
            config(['consumption.mode' => 'worker']);
            $id = $this->postJson('/api/front-of-house/tables/table-7/orders', ['lines' => [$this->salad, $this->steak]])->json('tableOrderId');
            $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();

            theKitchenAndTheDiningRoomCatchUp();

            expect(theFulfilmentOf($id)['status'])->toBe('confirmed')
                ->and(dishesOnTheScreenFor($id))->toBe(['Green salad', 'Steak frites']);
        });
    });

    describe('when some dishes of the order are 86', function () {
        beforeEach(function () {
            config(['consumption.mode' => 'worker']);
            // The kitchen runs out of steak, and the menu of the dining room has not heard of it yet.
            $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();
            $this->id = $this->postJson('/api/front-of-house/tables/table-7/orders', ['lines' => [$this->salad, $this->steak]])
                ->assertOk()
                ->json('tableOrderId');
            $this->steakOnly = $this->postJson('/api/front-of-house/tables/table-7/orders', ['lines' => [$this->steak]])->json('tableOrderId');
        });

        test('the available dishes go to the kitchen at once and the order awaits a revision, with the dishes unavailable', function () {
            theKitchenAndTheDiningRoomCatchUp();

            expect(theFulfilmentOf($this->id))->toBe([
                'tableOrderId' => $this->id,
                'tableId' => 'table-7',
                'status' => 'awaiting-revision',
                'reason' => null,
                'unavailableLines' => [['lineId' => "{$this->id}-2", 'name' => 'Steak frites', 'reason' => 'Steak frites is 86.']],
            ])->and(dishesOnTheScreenFor($this->id))->toBe(['Green salad']);
        });

        test('an order with no dish available opens no ticket and awaits a revision all the same', function () {
            theKitchenAndTheDiningRoomCatchUp();

            expect(theFulfilmentOf($this->steakOnly)['status'])->toBe('awaiting-revision')
                ->and(dishesOnTheScreenFor($this->steakOnly))->toBe([])
                ->and(loggedOfType('kitchen.lines_unavailable'))->toHaveCount(2);
        });

        test('the unavailable order does not block the orders behind it', function () {
            $behind = $this->postJson('/api/front-of-house/tables/table-7/orders', ['lines' => [$this->salad]])->json('tableOrderId');

            theKitchenAndTheDiningRoomCatchUp();

            expect(theFulfilmentOf($behind)['status'])->toBe('confirmed');
        });

        test('replaying the workers from the start changes nothing: one ticket, one line each, the same state', function () {
            theKitchenAndTheDiningRoomCatchUp();
            $before = theFulfilmentOf($this->id);

            app(CheckpointStore::class)->put('kitchen.open-ticket', 0);
            app(CheckpointStore::class)->put('foh.order-fulfilment', 0);
            theKitchenAndTheDiningRoomCatchUp();

            expect(theFulfilmentOf($this->id))->toBe($before)
                ->and(dishesOnTheScreenFor($this->id))->toBe(['Green salad'])
                ->and($this->getJson('/api/kitchen/screen')->json())->toHaveCount(1);
        });

        describe('and the waiter revises it', function () {
            beforeEach(fn () => theKitchenAndTheDiningRoomCatchUp());

            test('a replacement the kitchen can cook joins the ticket and confirms the order', function () {
                reviseWith($this->id, 'creme-brulee')->assertOk()->assertExactJson(['tableOrderId' => $this->id, 'accepted' => true]);

                theKitchenAndTheDiningRoomCatchUp();

                expect(theFulfilmentOf($this->id))->toMatchArray(['status' => 'confirmed', 'unavailableLines' => []])
                    ->and(dishesOnTheScreenFor($this->id))->toBe(['Green salad', 'Creme brulee']);
            });

            test('the revision is told as a fact that carries the added lines', function () {
                reviseWith($this->id, 'creme-brulee')->assertOk();

                $revised = loggedOfType('front_of_house.table_order_revised');

                expect($revised)->toHaveCount(1)
                    ->and($revised[0]->payload)->toBe([
                        'tableOrderId' => $this->id,
                        'tableId' => 'table-7',
                        'tableLabel' => 'Terrace 7',
                        'lines' => [['lineId' => "{$this->id}-3", 'name' => 'Creme brulee', 'quantity' => 1, 'course' => 'dessert', 'allergens' => ['dairy', 'eggs']]],
                    ]);
            });

            test('a replacement that is 86 too sends the order back to awaiting a revision, and the loop goes on', function () {
                $this->postJson('/api/kitchen/dishes/dish-brulee/eighty-six')->assertOk();

                reviseWith($this->id, 'creme-brulee')->assertOk();
                theKitchenAndTheDiningRoomCatchUp();

                expect(theFulfilmentOf($this->id))->toMatchArray([
                    'status' => 'awaiting-revision',
                    'unavailableLines' => [['lineId' => "{$this->id}-3", 'name' => 'Creme brulee', 'reason' => 'Creme brulee is 86.']],
                ])->and(dishesOnTheScreenFor($this->id))->toBe(['Green salad']);
            });

            test('can be revised several times until the kitchen can cook everything', function () {
                $this->postJson('/api/kitchen/dishes/dish-brulee/eighty-six')->assertOk();

                reviseWith($this->id, 'creme-brulee')->assertOk();
                theKitchenAndTheDiningRoomCatchUp();
                reviseWith($this->id, 'house-red')->assertOk();
                theKitchenAndTheDiningRoomCatchUp();

                expect(theFulfilmentOf($this->id))->toMatchArray(['status' => 'confirmed', 'unavailableLines' => []])
                    ->and(dishesOnTheScreenFor($this->id))->toBe(['Green salad', 'House red'])
                    ->and(loggedOfType('front_of_house.table_order_revised'))->toHaveCount(2);
            });

            test('a revision with one dish available and one 86 adds the first and awaits another revision', function () {
                $this->postJson('/api/kitchen/dishes/dish-brulee/eighty-six')->assertOk();

                reviseWith($this->id, 'house-red', 'creme-brulee')->assertOk();
                theKitchenAndTheDiningRoomCatchUp();

                expect(theFulfilmentOf($this->id)['status'])->toBe('awaiting-revision')
                    ->and(dishesOnTheScreenFor($this->id))->toBe(['Green salad', 'House red']);
            });

            test('the added lines reach the pass and the screen: the version moves for If-Match', function () {
                $before = $this->getJson('/api/kitchen/screen')->json()[0]['version'];

                reviseWith($this->id, 'house-red')->assertOk();
                theKitchenAndTheDiningRoomCatchUp();

                expect($this->getJson('/api/kitchen/screen')->json()[0]['version'])->toBeGreaterThan($before);
            });

            test('when the kitchen had no ticket at all, the revision opens it', function () {
                expect(dishesOnTheScreenFor($this->steakOnly))->toBe([]);

                reviseWith($this->steakOnly, 'green-salad')->assertOk();
                theKitchenAndTheDiningRoomCatchUp();

                expect(dishesOnTheScreenFor($this->steakOnly))->toBe(['Green salad'])
                    ->and(theFulfilmentOf($this->steakOnly)['status'])->toBe('confirmed');
            });

            test('an order that is not awaiting a revision cannot be revised', function () {
                reviseWith($this->id, 'house-red')->assertOk();
                theKitchenAndTheDiningRoomCatchUp();

                reviseWith($this->id, 'creme-brulee')
                    ->assertUnprocessable()
                    ->assertJson(['message' => 'Only an order awaiting a revision can be revised.']);
            });

            test('a revision with an item the menu refuses is turned down', function () {
                reviseWith($this->id, 'steak-frites')->assertUnprocessable();
            });

            test('replaying the kitchen after the revision does not throw the order back', function () {
                reviseWith($this->id, 'creme-brulee')->assertOk();
                theKitchenAndTheDiningRoomCatchUp();

                app(CheckpointStore::class)->put('kitchen.open-ticket', 0);
                theKitchenAndTheDiningRoomCatchUp();

                expect(theFulfilmentOf($this->id))->toMatchArray(['status' => 'confirmed', 'unavailableLines' => []])
                    ->and(dishesOnTheScreenFor($this->id))->toBe(['Green salad', 'Creme brulee']);
            });

            test('an unknown order cannot be revised', function () {
                reviseWith('nobody', 'house-red')->assertNotFound();
            });
        });

        describe('and the guests give up', function () {
            beforeEach(fn () => theKitchenAndTheDiningRoomCatchUp());

            test('the order is cancelled, and the dish already on the ticket is still cooked', function () {
                $this->postJson("/api/front-of-house/orders/{$this->id}/cancel")->assertOk()->assertExactJson(['tableOrderId' => $this->id, 'accepted' => true]);

                expect(theFulfilmentOf($this->id))->toMatchArray(['status' => 'cancelled', 'reason' => 'The guests gave up the dishes that were unavailable.'])
                    ->and(dishesOnTheScreenFor($this->id))->toBe(['Green salad']);
            });

            test('only the lines still unavailable are abandoned, and it is told as a fact', function () {
                $this->postJson('/api/kitchen/dishes/dish-brulee/eighty-six')->assertOk();
                reviseWith($this->id, 'creme-brulee')->assertOk();
                theKitchenAndTheDiningRoomCatchUp();

                $this->postJson("/api/front-of-house/orders/{$this->id}/cancel", ['reason' => 'They wanted a dessert.'])->assertOk();

                $cancelled = loggedOfType('front_of_house.table_order_cancelled');

                expect($cancelled)->toHaveCount(1)
                    ->and($cancelled[0]->payload)->toBe([
                        'tableOrderId' => $this->id,
                        'tableId' => 'table-7',
                        'reason' => 'They wanted a dessert.',
                        'abandonedLines' => ["{$this->id}-3"],
                    ])
                    ->and(dishesOnTheScreenFor($this->id))->toBe(['Green salad']);
            });

            test('cancelling twice tells the fact once', function () {
                $this->postJson("/api/front-of-house/orders/{$this->id}/cancel")->assertOk();
                $this->postJson("/api/front-of-house/orders/{$this->id}/cancel")->assertOk();

                expect(loggedOfType('front_of_house.table_order_cancelled'))->toHaveCount(1);
            });

            test('a cancelled order cannot be revised', function () {
                $this->postJson("/api/front-of-house/orders/{$this->id}/cancel")->assertOk();

                reviseWith($this->id, 'house-red')->assertUnprocessable();
            });

            test('an order the kitchen is cooking cannot be cancelled', function () {
                $cooked = $this->postJson('/api/front-of-house/tables/table-7/orders', ['lines' => [$this->salad]])->json('tableOrderId');
                theKitchenAndTheDiningRoomCatchUp();

                $this->postJson("/api/front-of-house/orders/{$cooked}/cancel")
                    ->assertUnprocessable()
                    ->assertJson(['message' => 'Only an order awaiting a revision can be cancelled.']);
            });

            test('nothing is billed for what was abandoned', function () {
                $this->postJson("/api/front-of-house/orders/{$this->id}/cancel")->assertOk();
                $this->postJson('/api/front-of-house/tables/table-7/bill')->assertOk();
                runWorkers('billing');

                $this->getJson('/api/billing/covers/table-7/bill')->assertNotFound();
            });
        });

        describe('and the guests pay and leave before the revision', function () {
            beforeEach(function () {
                theKitchenAndTheDiningRoomCatchUp();
                $ticket = $this->getJson('/api/kitchen/screen')->json()[0];
                $this->postJson("/api/kitchen/tickets/{$this->id}/fire", ['course' => 'starter'])->assertOk();
                $this->postJson("/api/kitchen/tickets/{$this->id}/lines/{$ticket['lines'][0]['lineId']}/ready")->assertOk();
                runWorkers('front-of-house');
                $dish = $this->getJson('/api/front-of-house/to-serve')->json()[0];
                $this->postJson("/api/front-of-house/to-serve/{$dish['id']}/serve")->assertOk();
                $this->postJson('/api/front-of-house/tables/table-7/bill')->assertOk();
                runWorkers('billing');
            });

            test('the table is not freed until the bill is paid', function () {
                runWorkers('front-of-house');

                $table = collect($this->getJson('/api/front-of-house/floor-plan')->json())->firstWhere('tableId', 'table-7');

                expect($table['status'])->toBe('occupied')
                    ->and(theFulfilmentOf($this->id)['status'])->toBe('awaiting-revision');
            });

            test('the revision is obsolete once the bill is paid: it is refused and the order is closed', function () {
                $bill = $this->getJson('/api/billing/covers/table-7/bill')->json();
                $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => $bill['balanceCents']])->assertOk();
                runWorkers('front-of-house');

                reviseWith($this->id, 'house-red')
                    ->assertStatus(412)
                    ->assertJson(['message' => 'The revision of the order is obsolete: the guests already paid and left.']);

                expect(theFulfilmentOf($this->id)['status'])->toBe('closed');
            });

            test('is refused even before the dining room has heard of the payment, because the table is already free', function () {
                DB::table(Tables::TABLES)
                    ->where('id', 'table-7')
                    ->update(['guest_count' => 0]);

                reviseWith($this->id, 'house-red')->assertStatus(412);
            });

            test('a payment the dining room hears twice closes the order once', function () {
                $bill = $this->getJson('/api/billing/covers/table-7/bill')->json();
                $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => $bill['balanceCents']])->assertOk();
                runWorkers('front-of-house');

                app(CheckpointStore::class)->put('foh.order-fulfilment', 0);
                theKitchenAndTheDiningRoomCatchUp();

                expect(theFulfilmentOf($this->id)['status'])->toBe('closed');
            });

            test('the payment of the previous guests does not close the order of the next ones', function () {
                $bill = $this->getJson('/api/billing/covers/table-7/bill')->json();
                $this->postJson("/api/billing/bills/{$bill['billId']}/payments", ['amountCents' => $bill['balanceCents']])->assertOk();
                runWorkers('front-of-house');
                $this->postJson('/api/front-of-house/tables/table-7/guests', ['guestCount' => 2])->assertOk();
                $this->postJson('/api/kitchen/dishes/dish-brulee/eighty-six')->assertOk();
                $next = $this->postJson('/api/front-of-house/tables/table-7/orders', ['lines' => [
                    ['menuItemId' => 'creme-brulee', 'quantity' => 1, 'course' => 'dessert'],
                ]])->json('tableOrderId');
                theKitchenAndTheDiningRoomCatchUp();

                app(CheckpointStore::class)->put('foh.order-fulfilment', 0);
                theKitchenAndTheDiningRoomCatchUp();

                expect(theFulfilmentOf($next)['status'])->toBe('awaiting-revision');
            });
        });
    });

    test('an order that is unknown has no fulfilment', function () {
        $this->getJson('/api/front-of-house/orders/nobody')->assertNotFound();
    });
});
