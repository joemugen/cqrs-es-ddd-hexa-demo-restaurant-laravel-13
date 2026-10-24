<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseNotFound;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\Modules\SharedKernel\Application\Concurrency\ConcurrencyConflict;
use Illuminate\Support\Facades\DB;

final class DatabaseTableOrderRepository implements TableOrderRepository
{
    public function exists(TableOrderId $id): bool
    {
        return DB::table(Tables::TABLE_ORDERS)->where('id', (string) $id)->exists();
    }

    public function get(TableOrderId $id): TableOrder
    {
        $order = DB::table(Tables::TABLE_ORDERS)->where('id', (string) $id)->first();

        if ($order === null) {
            throw new FrontOfHouseNotFound("Unknown order {$id}.");
        }

        return TableOrder::restore(
            $id,
            new TableId((string) $order->table_id),
            new VisitId((string) $order->visit_id),
            DB::table(Tables::TABLE_ORDER_LINES)->where('table_order_id', (string) $id)->orderBy('id')->get()
                ->map(fn (object $line): OrderLine => $this->line($line))
                ->all(),
            (int) $order->revisions,
        );
    }

    /**
     * An order is saved with all its lines: the ones it already has are left as they are.
     */
    public function save(TableOrder $order): void
    {
        $this->write($order);

        foreach ($order->lines() as $line) {
            DB::table(Tables::TABLE_ORDER_LINES)->insertOrIgnore([
                'id' => (string) $line->lineId,
                'table_order_id' => (string) $order->id(),
                'menu_item_id' => (string) $line->menuItemId,
                'name' => (string) $line->name,
                'quantity' => $line->quantity,
                'course' => $line->course,
                'unit_price_cents' => $line->unitPriceCents,
                'kind' => $line->kind,
                'allergens' => json_encode($line->allergens, JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /**
     * The order is written only if nobody revised it since it was read: the revisions it had then are its version, so the
     * guard holds with any database, not only with a writer at a time.
     */
    private function write(TableOrder $order): void
    {
        $row = [
            'table_id' => (string) $order->tableId(),
            'visit_id' => (string) $order->visitId(),
            'revisions' => $order->revisions(),
        ];

        $written = DB::table(Tables::TABLE_ORDERS)
            ->where('id', (string) $order->id())
            ->where('revisions', $order->revisionsWhenLoaded())
            ->update($row);

        if ($written > 0) {
            return;
        }

        if (! $this->exists($order->id())) {
            DB::table(Tables::TABLE_ORDERS)->insert(['id' => (string) $order->id()] + $row);

            return;
        }

        if ((int) DB::table(Tables::TABLE_ORDERS)->where('id', (string) $order->id())->value('revisions') !== $order->revisionsWhenLoaded()) {
            throw new ConcurrencyConflict("Order {$order->id()} was revised since it was read.");
        }
    }

    private function line(object $row): OrderLine
    {
        return new OrderLine(
            new LineId((string) $row->id),
            new MenuItemId((string) $row->menu_item_id),
            new DishName((string) $row->name),
            (int) $row->quantity,
            (string) $row->course,
            (int) $row->unit_price_cents,
            (string) $row->kind,
            array_values(json_decode((string) $row->allergens, true, 512, JSON_THROW_ON_ERROR)),
        );
    }
}
