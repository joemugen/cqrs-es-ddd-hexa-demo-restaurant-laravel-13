<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use Illuminate\Support\Facades\DB;

final class DatabaseOrderFulfilmentRepository implements OrderFulfilmentRepository
{
    public function exists(TableOrderId $id): bool
    {
        return DB::table(Tables::ORDER_FULFILMENTS)->where('table_order_id', (string) $id)->exists();
    }

    public function get(TableOrderId $id): OrderFulfilment
    {
        $row = DB::table(Tables::ORDER_FULFILMENTS)->where('table_order_id', (string) $id)->first();

        if ($row === null) {
            throw new FrontOfHouseException("Unknown order {$id}.", 404);
        }

        return OrderFulfilment::restore(
            new TableOrderId((string) $row->table_order_id),
            new TableId((string) $row->table_id),
            (string) $row->status,
            $row->reason !== null ? (string) $row->reason : null,
        );
    }

    public function save(OrderFulfilment $fulfilment): void
    {
        DB::table(Tables::ORDER_FULFILMENTS)->updateOrInsert(['table_order_id' => (string) $fulfilment->id()], [
            'table_id' => (string) $fulfilment->tableId(),
            'status' => $fulfilment->status(),
            'reason' => $fulfilment->reason(),
        ]);
    }
}
