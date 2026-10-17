<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListToServe;
use App\Providers\BoundedContexts\SharedKernel\Application\Query;
use App\Providers\BoundedContexts\SharedKernel\Application\QueryHandler;
use Illuminate\Support\Facades\DB;

/**
 * @implements QueryHandler<ListToServe>
 */
final class ListToServeHandler implements QueryHandler
{
    /**
     * @return list<array{id: string, tableId: string, tableLabel: string, dishName: string, quantity: int, ticketId: string, lineId: string}>
     */
    public function handle(Query $query): array
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
