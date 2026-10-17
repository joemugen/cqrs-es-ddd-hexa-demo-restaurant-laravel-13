<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Query;

use App\Providers\BoundedContexts\Kitchen\Application\Query\ListKitchenScreen;
use App\Providers\BoundedContexts\SharedKernel\Application\Query;
use App\Providers\BoundedContexts\SharedKernel\Application\QueryHandler;
use Illuminate\Support\Facades\DB;

/**
 * @implements QueryHandler<ListKitchenScreen>
 */
final class ListKitchenScreenHandler implements QueryHandler
{
    /**
     * @return list<array{ticketId: string, partyRef: string, tableLabel: string, done: bool, lines: list<array<string, mixed>>}>
     */
    public function handle(Query $query): array
    {
        $lines = DB::table('kitchen_screen_lines')->orderBy('id')->get()->groupBy('ticket_id');

        return DB::table('kitchen_screen_tickets')
            ->orderBy('ticket_id')
            ->get()
            ->map(function (object $ticket) use ($lines): array {
                $ticketLines = $lines->get($ticket->ticket_id, collect());

                return [
                    'ticketId' => (string) $ticket->ticket_id,
                    'partyRef' => (string) $ticket->party_ref,
                    'tableLabel' => (string) $ticket->table_label,
                    'done' => (bool) $ticket->done,
                    'lines' => $ticketLines->map(function (object $line): array {
                        $allergens = $line->allergens;
                        if (is_string($allergens)) {
                            $allergens = json_decode($allergens, true, 512, JSON_THROW_ON_ERROR);
                        }

                        return [
                            'lineId' => (string) $line->line_id,
                            'dishName' => (string) $line->dish_name,
                            'station' => (string) $line->station,
                            'cookingMinutes' => (int) $line->cooking_minutes,
                            'quantity' => (int) $line->quantity,
                            'course' => (string) $line->course,
                            'allergens' => is_array($allergens) ? array_values($allergens) : [],
                            'status' => (string) $line->status,
                        ];
                    })->values()->all(),
                ];
            })
            ->all();
    }
}
