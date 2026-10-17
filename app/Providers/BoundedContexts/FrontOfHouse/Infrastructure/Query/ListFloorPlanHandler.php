<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListFloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response\FloorPlanRow;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response\FloorPlanView;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\SharedKernel\Application\Query;
use App\Providers\BoundedContexts\SharedKernel\Application\QueryHandler;
use Illuminate\Support\Facades\DB;

/**
 * @implements QueryHandler<ListFloorPlan, FloorPlanView>
 */
final class ListFloorPlanHandler implements QueryHandler
{
    /**
     * The screen reads the floor-plan table. It never loads a Table.
     */
    public function handle(Query $query): FloorPlanView
    {
        return new FloorPlanView(DB::table('foh_floor_plan')
            ->orderBy('table_id')
            ->get()
            ->map(fn (object $row): FloorPlanRow => new FloorPlanRow(
                new TableId((string) $row->table_id),
                new TableLabel((string) $row->label),
                (int) $row->guest_count,
                (string) $row->status,
            ))
            ->all());
    }
}
