<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\StoredEvent;

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

describe('the order fulfilment saga', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-7/guests', ['guestCount' => 2])->assertOk();
        $this->steak = ['lines' => [['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main']]];
        $this->salad = ['lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']]];
    });

    describe('when the kitchen opens the ticket', function () {
        test('is confirmed, at once in sync mode', function () {
            $id = $this->postJson('/api/front-of-house/tables/table-7/orders', $this->salad)->json('tableOrderId');

            $this->getJson("/api/front-of-house/orders/{$id}")
                ->assertOk()
                ->assertExactJson(['tableOrderId' => $id, 'tableId' => 'table-7', 'status' => 'confirmed', 'reason' => null]);
        });

        test('goes from awaiting the kitchen to confirmed as the workers catch up', function () {
            config(['consumption.mode' => 'worker']);
            $id = $this->postJson('/api/front-of-house/tables/table-7/orders', $this->salad)->json('tableOrderId');

            $this->getJson("/api/front-of-house/orders/{$id}")->assertNotFound();

            runWorkers('front-of-house');
            $this->getJson("/api/front-of-house/orders/{$id}")->assertOk()->assertJsonPath('status', 'awaiting-kitchen');

            runWorkers('kitchen', 'front-of-house');
            $this->getJson("/api/front-of-house/orders/{$id}")->assertOk()->assertJsonPath('status', 'confirmed');
        });
    });

    describe('when the kitchen refuses an order the dining room had accepted', function () {
        beforeEach(function () {
            config(['consumption.mode' => 'worker']);
            // The kitchen runs out of steak, and the menu of the dining room has not heard of it yet.
            $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();
            $this->id = $this->postJson('/api/front-of-house/tables/table-7/orders', $this->steak)->assertOk()->json('tableOrderId');
        });

        test('the kitchen records the refusal as a fact and no ticket is opened, instead of failing forever', function () {
            runWorkers('kitchen');

            $refusals = loggedOfType('kitchen.order_refused');

            expect($refusals)->toHaveCount(1)
                ->and($refusals[0]->payload)->toBe(['tableOrderId' => $this->id, 'tableId' => 'table-7', 'reason' => 'Steak frites is 86.'])
                ->and($this->getJson('/api/kitchen/screen')->json())->toBe([]);
        });

        test('the dining room compensates: the order is cancelled with the reason, and told as a fact', function () {
            runWorkers('front-of-house', 'kitchen', 'front-of-house');

            $this->getJson("/api/front-of-house/orders/{$this->id}")
                ->assertOk()
                ->assertExactJson(['tableOrderId' => $this->id, 'tableId' => 'table-7', 'status' => 'cancelled', 'reason' => 'Steak frites is 86.']);

            $cancelled = loggedOfType('front_of_house.table_order_cancelled');

            expect($cancelled)->toHaveCount(1)
                ->and($cancelled[0]->payload)->toBe(['tableOrderId' => $this->id, 'tableId' => 'table-7', 'reason' => 'Steak frites is 86.']);
        });

        test('the refusal does not block the orders behind it', function () {
            $this->postJson('/api/front-of-house/tables/table-7/orders', $this->salad)->assertOk();

            runWorkers('kitchen');

            expect(collect($this->getJson('/api/kitchen/screen')->json())->pluck('lines.0.dishName')->all())->toBe(['Green salad']);
        });

        test('replaying the workers changes nothing: one refusal, one cancellation', function () {
            runWorkers('front-of-house', 'kitchen', 'front-of-house', 'kitchen', 'front-of-house');

            expect(loggedOfType('kitchen.order_refused'))->toHaveCount(1)
                ->and(loggedOfType('front_of_house.table_order_cancelled'))->toHaveCount(1);
        });

        test('nothing is billed for an order that was cancelled', function () {
            runWorkers('front-of-house', 'kitchen', 'front-of-house');
            $this->postJson('/api/front-of-house/tables/table-7/bill')->assertOk();
            runWorkers('billing');

            $this->getJson('/api/billing/covers/table-7/bill')->assertNotFound();
        });
    });

    test('an order that is unknown has no fulfilment', function () {
        $this->getJson('/api/front-of-house/orders/nobody')->assertNotFound();
    });
});
