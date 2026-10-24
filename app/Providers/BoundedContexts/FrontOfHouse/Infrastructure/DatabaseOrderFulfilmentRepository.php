<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FulfilmentStatus;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\UnavailableLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\Modules\SharedKernel\Domain\ConcurrencyConflict;
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

        return $this->fulfilment($row);
    }

    public function openOf(VisitId $visit): array
    {
        return DB::table(Tables::ORDER_FULFILMENTS)
            ->where('visit_id', (string) $visit)
            ->whereNotIn('status', [FulfilmentStatus::CANCELLED, FulfilmentStatus::CLOSED])
            ->get()
            ->map(fn (object $row): OrderFulfilment => $this->fulfilment($row))
            ->all();
    }

    /**
     * The order is written only if nobody saved it since it was read, whoever they are: the room over HTTP or a processor.
     */
    public function save(OrderFulfilment $fulfilment): void
    {
        $row = [
            'table_id' => (string) $fulfilment->tableId(),
            'visit_id' => (string) $fulfilment->visitId(),
            'status' => $fulfilment->status(),
            'reason' => $fulfilment->reason(),
            'unavailable_lines' => json_encode(
                array_map(fn (UnavailableLine $line): array => $line->toPayload(), $fulfilment->unavailableLines()),
                JSON_THROW_ON_ERROR,
            ),
            'request_position' => $fulfilment->requestPosition(),
            'revisions_seen' => $fulfilment->revisionsSeen(),
            'version' => $fulfilment->version() + 1,
        ];

        $written = DB::table(Tables::ORDER_FULFILMENTS)
            ->where('table_order_id', (string) $fulfilment->id())
            ->where('version', $fulfilment->version())
            ->update($row);

        if ($written === 0 && $this->exists($fulfilment->id())) {
            throw new ConcurrencyConflict("Order {$fulfilment->id()} was changed since it was read.");
        }

        if ($written === 0) {
            DB::table(Tables::ORDER_FULFILMENTS)->insert(['table_order_id' => (string) $fulfilment->id()] + $row);
        }
    }

    private function fulfilment(object $row): OrderFulfilment
    {
        return OrderFulfilment::restore(
            new TableOrderId((string) $row->table_order_id),
            new TableId((string) $row->table_id),
            new VisitId((string) $row->visit_id),
            (string) $row->status,
            $row->reason !== null ? (string) $row->reason : null,
            array_map(
                UnavailableLine::fromPayload(...),
                json_decode((string) ($row->unavailable_lines ?? '[]'), true, 512, JSON_THROW_ON_ERROR),
            ),
            (int) $row->request_position,
            (int) $row->revisions_seen,
            (int) $row->version,
        );
    }
}
