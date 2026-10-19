<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use Illuminate\Support\Facades\DB;

final class DatabaseTableOrderRepository implements TableOrderRepository
{
    public function exists(TableOrderId $id): bool
    {
        return DB::table(Tables::TABLE_ORDERS)->where('id', (string) $id)->exists();
    }

    public function save(TableOrder $order): void
    {
        DB::table(Tables::TABLE_ORDERS)->insert([
            'id' => (string) $order->id(),
            'table_id' => (string) $order->tableId(),
        ]);

        foreach ($order->lines() as $line) {
            DB::table(Tables::TABLE_ORDER_LINES)->insert([
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
}
