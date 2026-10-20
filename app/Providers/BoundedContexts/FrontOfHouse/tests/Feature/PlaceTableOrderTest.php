<?php

use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Menu;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItem;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Infrastructure\Tables as SharedKernelTables;
use Illuminate\Support\Facades\DB;

describe('placing a table order', function () {
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
            ->and(DB::table(Tables::TABLE_ORDER_LINES)->where('name', 'House red')->value('unit_price_cents'))->toBe(600);

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
        DB::table(Tables::MENU_ITEMS)->where('id', 'steak-frites')->update(['available' => false]);

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

    describe('the order id', function () {
        beforeEach(function () {
            $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
            $this->order = ['lines' => [['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter']]];
        });

        test('is a namespaced UUID', function () {
            $id = $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order)->json('tableOrderId');

            expect($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
        });

        test('differs from one order to the next when the caller gives no key', function () {
            $first = $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order)->json('tableOrderId');
            $second = $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order)->json('tableOrderId');

            expect($first)->not->toBe($second);
        });

        test('is the same for the same Idempotency-Key, and the order is placed once', function () {
            $headers = ['Idempotency-Key' => 'checkout-42'];

            $first = $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, $headers)->assertOk()->json('tableOrderId');
            $second = $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, $headers)->assertOk()->json('tableOrderId');

            expect($second)->toBe($first)
                ->and(DB::table(Tables::TABLE_ORDERS)->count())->toBe(1)
                ->and(DB::table(Tables::TABLE_ORDER_LINES)->count())->toBe(1)
                ->and(collect(app(EventStore::class)->readAllFrom(0))->filter(
                    fn ($event) => $event->type->is('front_of_house.table_order_placed'),
                ))->toHaveCount(1);
        });

        test('differs for another Idempotency-Key', function () {
            $first = $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, ['Idempotency-Key' => 'a'])->json('tableOrderId');
            $second = $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, ['Idempotency-Key' => 'b'])->json('tableOrderId');

            expect($second)->not->toBe($first);
        });

        test('replays the stored response when the same Idempotency-Key comes back', function () {
            $headers = ['Idempotency-Key' => 'checkout-42'];

            $first = $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, $headers)->assertOk();
            $second = $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, $headers)->assertOk();

            expect($second->json())->toBe($first->json())
                ->and(DB::table(SharedKernelTables::IDEMPOTENCY_KEYS)->count())->toBe(1);
        });

        test('refuses an Idempotency-Key reused with another order', function () {
            $headers = ['Idempotency-Key' => 'checkout-42'];
            $this->postJson('/api/front-of-house/tables/table-4/orders', $this->order, $headers)->assertOk();

            $this->postJson('/api/front-of-house/tables/table-4/orders', [
                'lines' => [['menuItemId' => 'house-red', 'quantity' => 3, 'course' => 'drink']],
            ], $headers)->assertUnprocessable()->assertJson([
                'message' => 'This Idempotency-Key was already used with another request.',
            ]);

            expect(DB::table(Tables::TABLE_ORDERS)->count())->toBe(1);
        });
    });
});
