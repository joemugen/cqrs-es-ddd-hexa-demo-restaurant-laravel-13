<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvent;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamId;

/**
 * @return list<StoredEvent>
 */
function factsOfType(string $type): array
{
    return array_values(array_filter(
        app(EventStore::class)->readAllFrom(0),
        fn (StoredEvent $event): bool => $event->type->is($type),
    ));
}

function kitchenWorker(): void
{
    test()->artisan('kitchen:consume', ['--stop-when-empty' => true])->doesntExpectOutputToContain('failed')->assertSuccessful();
}

/**
 * What the dining room tells the kitchen when the guests chose a replacement: a contract in the log, as the kitchen reads it.
 */
function reviseOrderWith(string $tableOrderId, string $menuItemName, string $lineId): int
{
    return app(EventStore::class)->append(
        new StreamId('integration-'.bin2hex(random_bytes(4))),
        0,
        RecordedEvents::of(RecordedEvent::now('front_of_house.table_order_revised', [
            'tableOrderId' => $tableOrderId,
            'tableId' => 'table-4',
            'tableLabel' => 'Window 4',
            'lines' => [['lineId' => $lineId, 'name' => $menuItemName, 'quantity' => 1, 'course' => 'dessert', 'allergens' => []]],
        ], 1)),
    );
}

describe('an order of which some dishes are 86', function () {
    beforeEach(function () {
        config(['consumption.mode' => 'worker']);
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();
        $this->partial = [
            'lines' => [
                ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
                ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
            ],
        ];
    });

    describe('when the kitchen reads it', function () {
        test('the available dishes go to the kitchen at once, on a ticket', function () {
            $this->postJson('/api/front-of-house/tables/table-4/orders', $this->partial)->assertOk();

            kitchenWorker();

            $screen = $this->getJson('/api/kitchen/screen')->json();

            expect($screen)->toHaveCount(1)
                ->and(array_column($screen[0]['lines'], 'dishName'))->toBe(['Green salad']);
        });

        test('the unavailable dishes are told as a fact, with the reason and the position of the order', function () {
            $id = $this->postJson('/api/front-of-house/tables/table-4/orders', $this->partial)->json('tableOrderId');

            kitchenWorker();

            $unavailable = factsOfType('kitchen.lines_unavailable');
            $placed = factsOfType('front_of_house.table_order_placed');

            expect($unavailable)->toHaveCount(1)
                ->and($unavailable[0]->payload)->toBe([
                    'tableOrderId' => $id,
                    'tableId' => 'table-4',
                    'orderPosition' => $placed[0]->position,
                    'lines' => [['lineId' => "{$id}-2", 'name' => 'Steak frites', 'reason' => 'Steak frites is 86.']],
                ]);
        });

        test('an order with nothing available opens no ticket and only tells what is unavailable', function () {
            $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [$this->partial['lines'][1]]])->assertOk();

            kitchenWorker();

            expect($this->getJson('/api/kitchen/screen')->json())->toBe([])
                ->and(factsOfType('kitchen.ticket_opened'))->toBe([])
                ->and(factsOfType('kitchen.lines_unavailable'))->toHaveCount(1);
        });

        test('an order with everything available says nothing about unavailable dishes', function () {
            $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [$this->partial['lines'][0]]])->assertOk();

            kitchenWorker();

            expect(factsOfType('kitchen.lines_unavailable'))->toBe([])
                ->and($this->getJson('/api/kitchen/screen')->json())->toHaveCount(1);
        });

        test('the orders behind it go on', function () {
            $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [$this->partial['lines'][1]]])->assertOk();
            $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [$this->partial['lines'][0]]])->assertOk();

            kitchenWorker();

            expect($this->getJson('/api/kitchen/screen')->json())->toHaveCount(1);
        });

        test('replaying the worker from the start opens no second ticket and adds no line twice', function () {
            $this->postJson('/api/front-of-house/tables/table-4/orders', $this->partial)->assertOk();
            kitchenWorker();

            app(CheckpointStore::class)->put('kitchen.open-ticket', 0);
            kitchenWorker();

            expect($this->getJson('/api/kitchen/screen')->json())->toHaveCount(1)
                ->and($this->getJson('/api/kitchen/screen')->json()[0]['lines'])->toHaveCount(1);
        });
    });

    describe('when the dining room revises the order', function () {
        beforeEach(function () {
            $this->id = $this->postJson('/api/front-of-house/tables/table-4/orders', $this->partial)->json('tableOrderId');
            kitchenWorker();
        });

        test('the replacement joins the ticket that is already open', function () {
            reviseOrderWith($this->id, 'Creme brulee', "{$this->id}-3");

            kitchenWorker();

            $screen = $this->getJson('/api/kitchen/screen')->json();

            expect($screen)->toHaveCount(1)
                ->and(array_column($screen[0]['lines'], 'dishName'))->toBe(['Green salad', 'Creme brulee']);
        });

        test('a replacement that is 86 too starts the loop again, with its own position', function () {
            $this->postJson('/api/kitchen/dishes/dish-brulee/eighty-six')->assertOk();
            $position = reviseOrderWith($this->id, 'Creme brulee', "{$this->id}-3");

            kitchenWorker();

            $unavailable = factsOfType('kitchen.lines_unavailable');

            expect($unavailable)->toHaveCount(2)
                ->and($unavailable[1]->payload['orderPosition'])->toBe($position)
                ->and($unavailable[1]->payload['lines'][0]['name'])->toBe('Creme brulee')
                ->and($this->getJson('/api/kitchen/screen')->json()[0]['lines'])->toHaveCount(1);
        });

        test('replaying the revision adds the line once', function () {
            reviseOrderWith($this->id, 'Creme brulee', "{$this->id}-3");
            kitchenWorker();

            app(CheckpointStore::class)->put('kitchen.open-ticket', 0);
            kitchenWorker();

            expect($this->getJson('/api/kitchen/screen')->json()[0]['lines'])->toHaveCount(2)
                ->and(factsOfType('kitchen.dishes_added'))->toHaveCount(1);
        });

        test('opens the ticket when no dish had been available at first', function () {
            $other = $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [$this->partial['lines'][1]]])->json('tableOrderId');
            kitchenWorker();
            expect(collect($this->getJson('/api/kitchen/screen')->json())->pluck('ticketId')->all())->not->toContain($other);

            reviseOrderWith($other, 'Creme brulee', "{$other}-2");
            kitchenWorker();

            expect(collect($this->getJson('/api/kitchen/screen')->json())->pluck('ticketId')->all())->toContain($other);
        });
    });
});

describe('a dish declared 86 after the order', function () {
    test('does not make the order partial: the position of the order in the log decides', function () {
        config(['consumption.mode' => 'worker']);
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main']],
        ])->assertOk();
        $this->postJson('/api/kitchen/dishes/dish-steak/eighty-six')->assertOk();

        kitchenWorker();

        expect(factsOfType('kitchen.lines_unavailable'))->toBe([])
            ->and($this->getJson('/api/kitchen/screen')->json())->toHaveCount(1);
    });
});
