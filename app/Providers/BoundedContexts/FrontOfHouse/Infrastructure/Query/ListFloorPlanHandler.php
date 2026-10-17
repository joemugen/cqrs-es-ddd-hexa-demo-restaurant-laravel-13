<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListFloorPlan;
use App\Providers\BoundedContexts\SharedKernel\Application\Query;
use App\Providers\BoundedContexts\SharedKernel\Application\QueryHandler;
use Illuminate\Support\Facades\DB;

/**
 * @implements QueryHandler<ListFloorPlan>
 */
final class ListFloorPlanHandler implements QueryHandler
{
    /**
     * The screen reads the floor-plan table. It never loads a Table.
     *
     * @return list<array{tableId: string, label: string, guestCount: int, status: string}>
     */
    public function handle(Query $query): array
    {
        return DB::table('foh_floor_plan')
            ->orderBy('table_id')
            ->get()
            ->map(fn (object $row): array => [
                'tableId' => (string) $row->table_id,
                'label' => (string) $row->label,
                'guestCount' => (int) $row->guest_count,
                'status' => (string) $row->status,
            ])
            ->all();
    }
}
