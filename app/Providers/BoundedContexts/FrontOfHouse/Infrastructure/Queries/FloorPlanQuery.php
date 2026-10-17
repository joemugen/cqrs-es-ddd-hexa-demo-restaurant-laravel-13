<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Queries;

use Illuminate\Support\Facades\DB;

final class FloorPlanQuery
{
    /**
     * The screen reads the floor-plan table. It never loads a Table.
     *
     * @return list<array{tableId: string, label: string, guestCount: int, status: string}>
     */
    public function all(): array
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
