<?php

use App\Providers\BoundedContexts\EventStore\Domain\EventStore;
use App\Providers\BoundedContexts\FrontOfHouse\Application\PassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;

beforeEach(function () {
    $this->seed(FrontOfHouseSeeder::class);
    $this->seed(KitchenSeeder::class);
    $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
    $this->postJson('/api/front-of-house/tables/table-4/orders', [
        'lines' => [
            ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
        ],
    ])->assertOk();
});

test('the pass stays empty until the queued projector runs', function () {
    config(['queue.default' => 'database']);

    $screen = $this->getJson('/api/kitchen/screen')->json();
    $ticketId = $screen[0]['ticketId'];
    $lineId = $screen[0]['lines'][0]['lineId'];

    $this->postJson("/api/kitchen/tickets/{$ticketId}/fire", ['course' => 'starter'])->assertOk();
    $this->postJson("/api/kitchen/tickets/{$ticketId}/lines/{$lineId}/ready")->assertOk();

    $this->getJson('/api/front-of-house/to-serve')->assertExactJson([]);

    $this->artisan('queue:work', ['--stop-when-empty' => true])->assertSuccessful();

    $waiting = $this->getJson('/api/front-of-house/to-serve')->assertOk()->json();
    expect($waiting)->toHaveCount(1)
        ->and($waiting[0]['dishName'])->toBe('Green salad')
        ->and($waiting[0]['tableId'])->toBe('table-4')
        ->and($waiting[0]['quantity'])->toBe(1);
});

test('serving a dish acknowledges the command and leaves the pass', function () {
    $screen = $this->getJson('/api/kitchen/screen')->json();
    $ticketId = $screen[0]['ticketId'];
    $lineId = $screen[0]['lines'][0]['lineId'];
    $this->postJson("/api/kitchen/tickets/{$ticketId}/fire", ['course' => 'starter'])->assertOk();
    $this->postJson("/api/kitchen/tickets/{$ticketId}/lines/{$lineId}/ready")->assertOk();

    $toServeId = $this->getJson('/api/front-of-house/to-serve')->json()[0]['id'];

    $this->postJson("/api/front-of-house/to-serve/{$toServeId}/serve")
        ->assertExactJson(['toServeId' => $toServeId, 'accepted' => true]);

    $this->getJson('/api/front-of-house/to-serve')->assertExactJson([]);

    $served = collect(app(EventStore::class)->readAllFrom(0))
        ->first(fn ($event) => $event->type->is('front_of_house.dish_served'));

    expect($served->payload)->toMatchArray([
        'tableId' => 'table-4',
        'description' => 'Green salad',
        'quantity' => 1,
        'unitPriceCents' => 1100,
        'kind' => 'food',
    ]);

    $this->postJson("/api/front-of-house/to-serve/{$toServeId}/serve")
        ->assertUnprocessable()
        ->assertJson(['message' => 'That dish has already left the pass.']);
});

test('the to-serve query does not load a pass aggregate', function () {
    $this->app->instance(PassRepository::class, new class implements PassRepository
    {
        public function find(string $id): DishAtThePass
        {
            throw new RuntimeException('Queries must not hydrate aggregates.');
        }

        public function save(DishAtThePass $dish): void
        {
            throw new RuntimeException('Queries must not hydrate aggregates.');
        }
    });

    $this->getJson('/api/front-of-house/to-serve')->assertOk();
});
