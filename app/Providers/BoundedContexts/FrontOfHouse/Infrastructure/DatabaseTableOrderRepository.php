<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use Illuminate\Support\Facades\DB;

final class DatabaseTableOrderRepository implements TableOrderRepository
{
    public function save(TableOrder $order): void
    {
        DB::table('foh_table_orders')->insert([
            'id' => $order->id(),
            'table_id' => $order->tableId(),
        ]);

        foreach ($order->lines() as $line) {
            DB::table('foh_table_order_lines')->insert([
                'id' => $line['lineId'],
                'table_order_id' => $order->id(),
                'menu_item_id' => $line['menuItemId'],
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'course' => $line['course'],
                'unit_price_cents' => $line['unitPriceCents'],
                'kind' => $line['kind'],
                'allergens' => json_encode($line['allergens'], JSON_THROW_ON_ERROR),
            ]);
        }
    }
}
