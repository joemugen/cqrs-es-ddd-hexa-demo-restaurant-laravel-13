<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Application\Command\ReceiveOrder;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenCommandBus;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Projection\KitchenScreenProjector;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Tables;
use App\Providers\Modules\EventStore\Domain\EventStore;
use Illuminate\Support\Facades\DB;

const ORDER_RECEIVED_AT = 100_000;

/**
 * @param  list<string>  $dishes  names of dishes, one line each
 */
function receivedFor(string $ticketId, string ...$dishes): ReceiveOrder
{
    return new ReceiveOrder(
        new KitchenTicketId($ticketId),
        new PartyRef('table-4'),
        new TableLabel('Window 4'),
        array_map(
            fn (string $dish, int $index): OrderedLine => new OrderedLine(new LineId("{$ticketId}-".(9 + $index)), new DishName($dish), 1, 'main', []),
            $dishes,
            array_keys($dishes),
        ),
        ORDER_RECEIVED_AT,
    );
}

describe('the kitchen receiving an order', function () {
    beforeEach(function () {
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->ticketId = $this->postJson('/api/front-of-house/tables/table-4/orders', [
            'lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']],
        ])->json('tableOrderId');
    });

    test('the dishes join the ticket on the screen, and the version moves so a stale If-Match is refused', function () {
        $before = $this->getJson('/api/kitchen/screen')->json()[0];

        app(KitchenCommandBus::class)->dispatch(receivedFor($this->ticketId, 'Steak frites'));

        $after = $this->getJson('/api/kitchen/screen')->json()[0];

        expect($after['lines'])->toHaveCount(2)
            ->and(collect($after['lines'])->firstWhere('dishName', 'Steak frites')['status'])->toBe('pending')
            ->and($after['version'])->toBe($before['version'] + 1);

        $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'main'], ['If-Match' => (string) $before['version']])
            ->assertStatus(412);
    });

    test('receiving the same lines again changes nothing', function () {
        app(KitchenCommandBus::class)->dispatch(receivedFor($this->ticketId, 'Steak frites'));
        app(KitchenCommandBus::class)->dispatch(receivedFor($this->ticketId, 'Steak frites'));

        $added = array_filter(app(EventStore::class)->readAllFrom(0), fn ($event) => $event->type->is('kitchen.dishes_added'));

        expect($added)->toHaveCount(1)
            ->and($this->getJson('/api/kitchen/screen')->json()[0]['lines'])->toHaveCount(2);
    });

    test('a ticket that was done is waiting for the new dishes', function () {
        $line = $this->getJson('/api/kitchen/screen')->json()[0]['lines'][0]['lineId'];
        $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'starter'])->assertOk();
        $this->postJson("/api/kitchen/tickets/{$this->ticketId}/lines/{$line}/ready")->assertOk();
        expect($this->getJson('/api/kitchen/screen')->json()[0]['done'])->toBeTrue();

        app(KitchenCommandBus::class)->dispatch(receivedFor($this->ticketId, 'Steak frites'));

        expect($this->getJson('/api/kitchen/screen')->json()[0]['done'])->toBeFalse();
    });

    test('the screen rebuilt from the stream shows the added dishes', function () {
        app(KitchenCommandBus::class)->dispatch(receivedFor($this->ticketId, 'Steak frites'));

        app(KitchenScreenProjector::class)->rebuild();

        expect($this->getJson('/api/kitchen/screen')->json()[0]['lines'])->toHaveCount(2);
    });

    test('opens the ticket when the kitchen had none yet', function () {
        app(KitchenCommandBus::class)->dispatch(receivedFor('order-without-ticket', 'Steak frites'));

        $screen = collect($this->getJson('/api/kitchen/screen')->json())->firstWhere('ticketId', 'order-without-ticket');

        expect($screen['lines'])->toHaveCount(1)
            ->and($screen['partyRef'])->toBe('table-4');
    });

    test('cooks what it can and tells the rest as a fact, in that order', function () {
        DB::table(Tables::DISHES)->where('name', 'Steak frites')->update(['in_stock' => false, 'eighty_sixed_position' => 1]);

        app(KitchenCommandBus::class)->dispatch(receivedFor($this->ticketId, 'Creme brulee', 'Steak frites'));

        $unavailable = array_values(array_filter(app(EventStore::class)->readAllFrom(0), fn ($event) => $event->type->is('kitchen.lines_unavailable')));
        $opened = array_values(array_filter(app(EventStore::class)->readAllFrom(0), fn ($event) => $event->type->is('ticket.dishes_added')));

        expect($this->getJson('/api/kitchen/screen')->json()[0]['lines'])->toHaveCount(2)
            ->and($unavailable)->toHaveCount(1)
            ->and($unavailable[0]->payload['orderPosition'])->toBe(ORDER_RECEIVED_AT)
            ->and($unavailable[0]->payload['lines'][0]['reason'])->toBe('Steak frites is 86.')
            ->and($unavailable[0]->position)->toBeGreaterThan($opened[0]->position);
    });

    test('opens no ticket when nothing can be cooked, and only tells it', function () {
        DB::table(Tables::DISHES)->where('name', 'Steak frites')->update(['in_stock' => false]);

        app(KitchenCommandBus::class)->dispatch(receivedFor('order-all-86', 'Steak frites'));

        expect(collect($this->getJson('/api/kitchen/screen')->json())->pluck('ticketId')->all())->not->toContain('order-all-86')
            ->and(array_filter(app(EventStore::class)->readAllFrom(0), fn ($event) => $event->type->is('kitchen.lines_unavailable')))->toHaveCount(1);
    });
});
