<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListToServe;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response\ToServeEntry;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response\ToServeView;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use Illuminate\Support\Facades\DB;

/**
 * @implements QueryHandler<ListToServe, ToServeView>
 */
#[AsQueryHandler(ListToServe::class)]
final class ListToServeHandler implements QueryHandler
{
    public function __invoke(ListToServe $query): ToServeView
    {
        return new ToServeView(DB::table(Tables::TO_SERVE)
            ->orderBy('position')
            ->get()
            ->map(fn (object $row): ToServeEntry => new ToServeEntry(
                new DishAtThePassId((string) $row->id),
                new TableId((string) $row->table_id),
                new TableLabel((string) $row->table_label),
                new DishName((string) $row->dish_name),
                (int) $row->quantity,
                new TableOrderId((string) $row->table_order_id),
                new LineId((string) $row->line_id),
            ))
            ->all());
    }
}
