<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Application\Command\AddDishesToTicket;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenCommandBus;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Domain\DomainEvent;
use App\Providers\Modules\SharedKernel\Domain\DomainEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamId;
use Illuminate\Support\Facades\DB;

/**
 * @return list<StoredEvent>
 */
function loggedAs(string $type): array
{
    return array_values(array_filter(
        app(EventStore::class)->readAllFrom(0),
        fn (StoredEvent $event): bool => $event->type->is($type),
    ));
}

function creamBruleeAddedTo(string $order): AddDishesToTicket
{
    return new AddDishesToTicket(
        new KitchenTicketId($order),
        new PartyRef('table-4'),
        new TableLabel('Window 4'),
        [new TicketLine(new LineId("{$order}-9"), new DishName('Creme brulee'), 'pastry', 8, 1, 'dessert', ['dairy'])],
    );
}

describe('the dishes added to a ticket', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->order = $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']],
        ])->json('tableOrderId');
    });

    test('are a fact of the ticket under its own name, and a contract for the others under the public one', function () {
        app(KitchenCommandBus::class)->dispatch(creamBruleeAddedTo($this->order));

        $internal = loggedAs('ticket.dishes_added');
        $published = loggedAs('kitchen.dishes_added');

        expect($internal)->toHaveCount(1)
            ->and((string) $internal[0]->streamId)->toBe("kitchen-ticket-{$this->order}")
            ->and($published)->toHaveCount(1)
            ->and($published[0]->payload)->toBe([
                'tableOrderId' => $this->order,
                'lines' => [['lineId' => "{$this->order}-9", 'name' => 'Creme brulee', 'quantity' => 1, 'course' => 'dessert']],
            ])
            ->and($published[0]->position)->toBeGreaterThan($internal[0]->position);
    });

    test('carry the version of the contract in the envelope, not in the payload', function () {
        app(KitchenCommandBus::class)->dispatch(creamBruleeAddedTo($this->order));

        expect(loggedAs('kitchen.dishes_added')[0]->contractVersion)->toBe(1)
            ->and(loggedAs('kitchen.dishes_added')[0]->payload)->not->toHaveKey('version')
            ->and(loggedAs('ticket.dishes_added')[0]->contractVersion)->toBeNull();
    });

    test('are published in the transaction of the command: nothing is published when it fails', function () {
        $before = count(app(EventStore::class)->readAllFrom(0));

        expect(fn () => app(KitchenCommandBus::class)->dispatch(new AddDishesToTicket(
            new KitchenTicketId($this->order),
            new PartyRef('table-4'),
            new TableLabel('Window 4'),
            [],
        )))->toThrow(Exception::class);

        expect(count(app(EventStore::class)->readAllFrom(0)))->toBe($before);
    });

    test('are not published a second time when the same dishes are added again', function () {
        app(KitchenCommandBus::class)->dispatch(creamBruleeAddedTo($this->order));
        app(KitchenCommandBus::class)->dispatch(creamBruleeAddedTo($this->order));

        expect(loggedAs('kitchen.dishes_added'))->toHaveCount(1);
    });

    test('are not announced for a fact that stays inside the ticket', function () {
        $this->postJson("/api/kitchen/tickets/{$this->order}/fire", ['course' => 'starter'])->assertOk();

        expect(array_map(fn (StoredEvent $event): string => (string) $event->type, loggedAs('kitchen.dish_fired')))->toHaveCount(1);
    });
});

describe('the envelope of the event store', function () {
    test('keeps the version of a contract next to the event and reads it back', function () {
        app(EventStore::class)->append(
            new StreamId('integration-test'),
            0,
            DomainEvents::of(DomainEvent::now('kitchen.something', ['a' => 1], 2)),
        );

        expect(DB::table('events')->where('stream_id', 'integration-test')->value('contract_version'))->toBe(2)
            ->and(loggedAs('kitchen.something')[0]->contractVersion)->toBe(2);
    });
});
