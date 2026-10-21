<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
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
            throw new FrontOfHouseException("Unknown order {$id}.", 404);
        }

        return TableOrder::restore(
            $id,
            new TableId((string) $order->table_id),
            DB::table(Tables::TABLE_ORDER_LINES)->where('table_order_id', (string) $id)->orderBy('id')->get()
                ->map(fn (object $line): OrderLine => $this->line($line))
                ->all(),
        );
    }

    /**
     * An order is saved with all its lines: the ones it already has are left as they are.
     */
    public function save(TableOrder $order): void
    {
        DB::table(Tables::TABLE_ORDERS)->insertOrIgnore([
            'id' => (string) $order->id(),
            'table_id' => (string) $order->tableId(),
        ]);

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
