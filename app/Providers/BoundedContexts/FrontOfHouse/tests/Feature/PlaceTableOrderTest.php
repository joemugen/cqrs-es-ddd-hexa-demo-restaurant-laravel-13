<?php

use App\Providers\BoundedContexts\EventStore\Domain\EventStore;
use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Menu;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItem;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(FrontOfHouseSeeder::class);
});

test('placing an order acknowledges it and does not return the menu', function () {
    $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();

    $response = $this->postJson('/api/front-of-house/tables/table-4/orders', [
        'lines' => [
            ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
            ['menuItemId' => 'house-red', 'quantity' => 2, 'course' => 'drink'],
        ],
    ]);

    $response->assertOk();
    expect($response->json())->toBe([
        'tableOrderId' => $response->json('tableOrderId'),
        'accepted' => true,
    ]);

    $event = app(EventStore::class)->readAllFrom(0)[0];
    expect($event->type->is('front_of_house.table_order_placed'))->toBeTrue()
        ->and($event->payload['tableLabel'])->toBe('Window 4')
        ->and($event->payload['lines'][0])->toBe([
            'lineId' => $response->json('tableOrderId').'-1',
            'name' => 'Green salad',
            'quantity' => 1,
            'course' => 'starter',
            'allergens' => [],
        ])
        ->and($event->payload['lines'][0])->not->toHaveKey('unitPriceCents')
        ->and(DB::table('foh_table_order_lines')->where('name', 'House red')->value('unit_price_cents'))->toBe(600);

    $this->getJson('/api/kitchen/screen')->assertExactJson([]);
});

test('a free table cannot take an order', function () {
    $this->postJson('/api/front-of-house/tables/table-4/orders', [
        'lines' => [
            ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
        ],
    ])->assertUnprocessable()->assertJson([
        'message' => 'No order for a table without seated guests.',
    ]);
});

test('an empty order is refused by the aggregate', function () {
    $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();

    $this->postJson('/api/front-of-house/tables/table-4/orders', [
        'lines' => [],
    ])->assertUnprocessable()->assertJson([
        'message' => 'An order needs at least one item.',
    ]);
});

test('an unavailable item is refused', function () {
    $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
    DB::table('foh_menu_items')->where('id', 'steak-frites')->update(['available' => false]);

    $this->postJson('/api/front-of-house/tables/table-4/orders', [
        'lines' => [
            ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
        ],
    ])->assertUnprocessable()->assertJson([
        'message' => 'Steak frites is unavailable.',
    ]);
});

test('the menu query does not load the menu aggregate', function () {
    $this->app->instance(MenuRepository::class, new class implements MenuRepository
    {
        public function containing(array $ids): Menu
        {
            throw new RuntimeException('Queries must not hydrate aggregates.');
        }

        public function findByName(DishName $name): MenuItem
        {
            throw new RuntimeException('Queries must not hydrate aggregates.');
        }

        public function save(MenuItem $item): void
        {
            throw new RuntimeException('Queries must not hydrate aggregates.');
        }
    });

    $this->getJson('/api/front-of-house/menu')
        ->assertOk()
        ->assertJsonFragment([
            'menuItemId' => 'green-salad',
            'name' => 'Green salad',
            'priceCents' => 1100,
            'available' => true,
            'kind' => 'food',
        ])
        ->assertJsonMissing(['station' => 'grill']);
});
