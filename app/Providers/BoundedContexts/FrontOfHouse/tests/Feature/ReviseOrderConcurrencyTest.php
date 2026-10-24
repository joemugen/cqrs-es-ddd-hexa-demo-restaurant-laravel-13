<?php

use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FulfilmentStatus;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\KitchenSeeder;
use App\Providers\Modules\SharedKernel\Domain\ConcurrencyConflict;
use Illuminate\Support\Facades\DB;

function aRevisionOfTheOrderWith(TableOrder $order, string $menuItem): void
{
    $order->revise(
        app(TableRepository::class)->get(new TableId('table-4')),
        app(MenuRepository::class)->containing([new MenuItemId($menuItem)]),
        [new RequestedLine(new MenuItemId($menuItem), 1, 'dessert')],
    );
}

function theLinesOfTheOrder(string $id): array
{
    return DB::table(Tables::TABLE_ORDER_LINES)->where('table_order_id', $id)->orderBy('id')->pluck('name')->all();
}

/**
 * A repository that conflicts on its next saves once it is told to, then behaves.
 */
function aRepositoryThatConflicts(TableOrderRepository $real): TableOrderRepository
{
    return new class($real) implements TableOrderRepository
    {
        public int $conflictsToCome = 0;

        public int $saves = 0;

        public function __construct(private readonly TableOrderRepository $real) {}

        public function exists(TableOrderId $id): bool
        {
            return $this->real->exists($id);
        }

        public function get(TableOrderId $id): TableOrder
        {
            return $this->real->get($id);
        }

        public function save(TableOrder $order): void
        {
            $this->saves++;

            if ($this->conflictsToCome > 0) {
                $this->conflictsToCome--;

                throw new ConcurrencyConflict('Order was revised since it was read.');
            }

            $this->real->save($order);
        }
    };
}

describe('two revisions of one order', function () {
    beforeEach(function () {
        $this->orders = aRepositoryThatConflicts(app(TableOrderRepository::class));
        $this->app->instance(TableOrderRepository::class, $this->orders);
        $this->seed(FrontOfHouseSeeder::class);
        $this->seed(KitchenSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->id = $this->postJson('/api/front-of-house/tables/table-4/orders', ['lines' => [['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main']]])->json('tableOrderId');
        DB::table(Tables::ORDER_FULFILMENTS)->where('table_order_id', $this->id)->update(['status' => FulfilmentStatus::AWAITING_REVISION]);
        $this->orders->saves = 0;
    });

    test('that read the same order cannot both be saved: the second one finds the order changed, whatever the database', function () {
        $orders = $this->orders;
        $first = $orders->get(new TableOrderId($this->id));
        $second = $orders->get(new TableOrderId($this->id));
        aRevisionOfTheOrderWith($first, 'creme-brulee');
        aRevisionOfTheOrderWith($second, 'house-red');
        $orders->save($first);

        expect(fn () => $orders->save($second))->toThrow(ConcurrencyConflict::class)
            ->and(theLinesOfTheOrder($this->id))->toBe(['Steak frites', 'Creme brulee'])
            ->and(DB::table(Tables::TABLE_ORDERS)->where('id', $this->id)->value('revisions'))->toBe(1);
    });

    test('are replayed by the bus of the room when the order changed meanwhile: the revision goes through once', function () {
        $this->orders->conflictsToCome = 1;

        $this->postJson("/api/front-of-house/orders/{$this->id}/revisions", ['lines' => [['menuItemId' => 'house-red', 'quantity' => 1, 'course' => 'drink']]])->assertOk();

        expect($this->orders->saves)->toBe(2)
            ->and(theLinesOfTheOrder($this->id))->toBe(['Steak frites', 'House red'])
            ->and(DB::table(Tables::TABLE_ORDERS)->where('id', $this->id)->value('revisions'))->toBe(1);
    });

    test('are refused with a 409 when the order keeps changing under them', function () {
        $this->orders->conflictsToCome = 10;

        $this->postJson("/api/front-of-house/orders/{$this->id}/revisions", ['lines' => [['menuItemId' => 'house-red', 'quantity' => 1, 'course' => 'drink']]])->assertStatus(409);

        expect($this->orders->saves)->toBe(3)
            ->and(theLinesOfTheOrder($this->id))->toBe(['Steak frites']);
    });
});
