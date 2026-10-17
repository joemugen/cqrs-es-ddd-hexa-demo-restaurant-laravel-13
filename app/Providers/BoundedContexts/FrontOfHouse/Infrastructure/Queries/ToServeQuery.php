<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Queries;

use Illuminate\Support\Facades\DB;

final class ToServeQuery
{
    /**
     * @return list<array{id: string, tableId: string, tableLabel: string, dishName: string, quantity: int, ticketId: string, lineId: string}>
     */
    public function waiting(): array
    {
        return DB::table('foh_to_serve')
            ->whereNull('served_at')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'tableId' => (string) $row->table_id,
                'tableLabel' => (string) $row->table_label,
                'dishName' => (string) $row->dish_name,
                'quantity' => (int) $row->quantity,
                'ticketId' => (string) $row->ticket_id,
                'lineId' => (string) $row->line_id,
            ])
            ->all();
    }
}
