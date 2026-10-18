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
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TicketId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\BoundedContexts\SharedKernel\Application\Query;
use App\Providers\BoundedContexts\SharedKernel\Application\QueryHandler;
use Illuminate\Support\Facades\DB;

/**
 * @implements QueryHandler<ListToServe, ToServeView>
 */
final class ListToServeHandler implements QueryHandler
{
    public function handle(Query $query): ToServeView
    {
        return new ToServeView(DB::table(Tables::TO_SERVE)
            ->whereNull('served_at')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): ToServeEntry => new ToServeEntry(
                new DishAtThePassId((string) $row->id),
                new TableId((string) $row->table_id),
                new TableLabel((string) $row->table_label),
                new DishName((string) $row->dish_name),
                (int) $row->quantity,
                new TicketId((string) $row->ticket_id),
                new LineId((string) $row->line_id),
            ))
            ->all());
    }
}
