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
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Projection\KitchenScreenProjector;
use App\Providers\Modules\EventStore\Domain\EventStore;

function steakAddedTo(string $ticketId): AddDishesToTicket
{
    return new AddDishesToTicket(
        new KitchenTicketId($ticketId),
        new PartyRef('table-4'),
        new TableLabel('Window 4'),
        [new TicketLine(new LineId("{$ticketId}-9"), new DishName('Steak frites'), 'grill', 12, 1, 'main', ['gluten'])],
    );
}

describe('adding dishes to a kitchen ticket', function () {
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

        app(KitchenCommandBus::class)->dispatch(steakAddedTo($this->ticketId));

        $after = $this->getJson('/api/kitchen/screen')->json()[0];

        expect($after['lines'])->toHaveCount(2)
            ->and(collect($after['lines'])->firstWhere('dishName', 'Steak frites')['status'])->toBe('pending')
            ->and($after['version'])->toBe($before['version'] + 1);

        $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'main'], ['If-Match' => (string) $before['version']])
            ->assertStatus(412);
    });

    test('adding the same dishes again changes nothing', function () {
        app(KitchenCommandBus::class)->dispatch(steakAddedTo($this->ticketId));
        app(KitchenCommandBus::class)->dispatch(steakAddedTo($this->ticketId));

        $added = array_filter(app(EventStore::class)->readAllFrom(0), fn ($event) => $event->type->is('kitchen.dishes_added'));

        expect($added)->toHaveCount(1)
            ->and($this->getJson('/api/kitchen/screen')->json()[0]['lines'])->toHaveCount(2);
    });

    test('a ticket that was done is waiting for the new dishes', function () {
        $line = $this->getJson('/api/kitchen/screen')->json()[0]['lines'][0]['lineId'];
        $this->postJson("/api/kitchen/tickets/{$this->ticketId}/fire", ['course' => 'starter'])->assertOk();
        $this->postJson("/api/kitchen/tickets/{$this->ticketId}/lines/{$line}/ready")->assertOk();
        expect($this->getJson('/api/kitchen/screen')->json()[0]['done'])->toBeTrue();

        app(KitchenCommandBus::class)->dispatch(steakAddedTo($this->ticketId));

        expect($this->getJson('/api/kitchen/screen')->json()[0]['done'])->toBeFalse();
    });

    test('the screen rebuilt from the stream shows the added dishes', function () {
        app(KitchenCommandBus::class)->dispatch(steakAddedTo($this->ticketId));

        app(KitchenScreenProjector::class)->rebuild();

        expect($this->getJson('/api/kitchen/screen')->json()[0]['lines'])->toHaveCount(2);
    });

    test('opens the ticket when the kitchen had none yet', function () {
        app(KitchenCommandBus::class)->dispatch(steakAddedTo('order-without-ticket'));

        $screen = collect($this->getJson('/api/kitchen/screen')->json())->firstWhere('ticketId', 'order-without-ticket');

        expect($screen['lines'])->toHaveCount(1)
            ->and($screen['partyRef'])->toBe('table-4');
    });
});
