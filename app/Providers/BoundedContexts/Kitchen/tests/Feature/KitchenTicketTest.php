<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Projection\KitchenScreenProjector;

beforeEach(function () {
    $this->seed(FrontOfHouseSeeder::class);
    $this->seed(KitchenSeeder::class);
    $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
    $this->postJson('/api/front-of-house/tables/table-4/orders', [
        'lines' => [
            ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
            ['menuItemId' => 'house-red', 'quantity' => 2, 'course' => 'drink'],
        ],
    ])->assertOk();
});

test('an order opens a kitchen ticket on the screen with no price', function () {
    $screen = $this->getJson('/api/kitchen/screen')->assertOk()->json();

    expect($screen)->toHaveCount(1)
        ->and($screen[0]['partyRef'])->toBe('table-4')
        ->and($screen[0]['tableLabel'])->toBe('Window 4')
        ->and($screen[0]['done'])->toBeFalse()
        ->and($screen[0]['lines'])->toHaveCount(3);

    $salad = collect($screen[0]['lines'])->firstWhere('dishName', 'Green salad');

    expect($salad)->toMatchArray([
        'station' => 'garnish',
        'cookingMinutes' => 4,
        'course' => 'starter',
        'status' => 'pending',
        'allergens' => [],
    ])->and($salad)->not->toHaveKey('priceCents');
});

test('the kitchen screen query does not load the ticket aggregate', function () {
    $this->app->instance(KitchenTicketRepository::class, new class implements KitchenTicketRepository
    {
        public function exists(string $id): bool
        {
            throw new RuntimeException('Queries must not hydrate aggregates.');
        }

        public function get(string $id): KitchenTicket
        {
            throw new RuntimeException('Queries must not hydrate aggregates.');
        }

        public function save(KitchenTicket $ticket): void
        {
            throw new RuntimeException('Queries must not hydrate aggregates.');
        }
    });

    $this->getJson('/api/kitchen/screen')->assertOk();
});

test('starters are fired and the ticket is done only when every dish is ready', function () {
    $screen = $this->getJson('/api/kitchen/screen')->json();
    $ticketId = $screen[0]['ticketId'];
    $lines = collect($screen[0]['lines'])->keyBy('dishName');

    $this->postJson("/api/kitchen/tickets/{$ticketId}/lines/{$lines['Green salad']['lineId']}/ready")
        ->assertUnprocessable()
        ->assertJson(['message' => 'Fire the dish before it can be ready.']);

    $this->postJson("/api/kitchen/tickets/{$ticketId}/fire", ['course' => 'starter'])
        ->assertExactJson(['kitchenTicketId' => $ticketId, 'accepted' => true]);

    $this->postJson("/api/kitchen/tickets/{$ticketId}/lines/{$lines['Green salad']['lineId']}/ready")
        ->assertExactJson(['kitchenTicketId' => $ticketId, 'accepted' => true]);

    $afterStarter = collect($this->getJson('/api/kitchen/screen')->json()[0]['lines'])->keyBy('dishName');
    expect($afterStarter['Green salad']['status'])->toBe('ready')
        ->and($afterStarter['Steak frites']['status'])->toBe('pending')
        ->and($this->getJson('/api/kitchen/screen')->json()[0]['done'])->toBeFalse();

    $this->postJson("/api/kitchen/tickets/{$ticketId}/fire", ['course' => 'main'])->assertOk();
    $this->postJson("/api/kitchen/tickets/{$ticketId}/fire", ['course' => 'drink'])->assertOk();
    $this->postJson("/api/kitchen/tickets/{$ticketId}/lines/{$lines['Steak frites']['lineId']}/ready")->assertOk();
    $this->postJson("/api/kitchen/tickets/{$ticketId}/lines/{$lines['House red']['lineId']}/ready")->assertOk();

    expect($this->getJson('/api/kitchen/screen')->json()[0]['done'])->toBeTrue();
});

test('the screen can be rebuilt by replaying the stream', function () {
    app(KitchenScreenProjector::class)->rebuild();

    expect($this->getJson('/api/kitchen/screen')->json()[0]['lines'])->toHaveCount(3);
});

test('a stale ticket version is refused', function () {
    $ticketId = $this->getJson('/api/kitchen/screen')->json()[0]['ticketId'];
    $first = app(KitchenTicketRepository::class)->get($ticketId);
    $second = app(KitchenTicketRepository::class)->get($ticketId);

    $first->fireCourse('starter');
    app(KitchenTicketRepository::class)->save($first);

    $second->fireCourse('starter');

    try {
        app(KitchenTicketRepository::class)->save($second);
        throw new RuntimeException('A stale version was accepted.');
    } catch (KitchenException $exception) {
        expect($exception->status())->toBe(409);
    }
});
