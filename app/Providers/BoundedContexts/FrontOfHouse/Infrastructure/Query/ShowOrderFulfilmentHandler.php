<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response\OrderFulfilmentView;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ShowOrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\UnavailableLine;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use Illuminate\Support\Facades\DB;

/**
 * @implements QueryHandler<ShowOrderFulfilment, OrderFulfilmentView>
 */
#[AsQueryHandler(ShowOrderFulfilment::class)]
final class ShowOrderFulfilmentHandler implements QueryHandler
{
    public function __invoke(ShowOrderFulfilment $query): OrderFulfilmentView
    {
        $row = DB::table(Tables::ORDER_FULFILMENTS)->where('table_order_id', (string) $query->tableOrderId)->first();

        if ($row === null) {
            throw new FrontOfHouseException("Unknown order {$query->tableOrderId}.", 404);
        }

        return new OrderFulfilmentView(
            new TableOrderId((string) $row->table_order_id),
            new TableId((string) $row->table_id),
            (string) $row->status,
            $row->reason !== null ? (string) $row->reason : null,
            array_map(
                UnavailableLine::fromPayload(...),
                json_decode((string) ($row->unavailable_lines ?? '[]'), true, 512, JSON_THROW_ON_ERROR),
            ),
        );
    }
}
