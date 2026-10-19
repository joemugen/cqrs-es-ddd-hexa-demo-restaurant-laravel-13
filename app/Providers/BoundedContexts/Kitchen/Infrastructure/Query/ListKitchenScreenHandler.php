<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Query;

use App\Providers\BoundedContexts\Kitchen\Application\Query\ListKitchenScreen;
use App\Providers\BoundedContexts\Kitchen\Application\Query\Response\KitchenScreenLine;
use App\Providers\BoundedContexts\Kitchen\Application\Query\Response\KitchenScreenTicket;
use App\Providers\BoundedContexts\Kitchen\Application\Query\Response\KitchenScreenView;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Tables;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use Illuminate\Support\Facades\DB;

/**
 * @implements QueryHandler<ListKitchenScreen, KitchenScreenView>
 */
#[AsQueryHandler(ListKitchenScreen::class)]
final class ListKitchenScreenHandler implements QueryHandler
{
    public function __invoke(ListKitchenScreen $query): KitchenScreenView
    {
        $lines = DB::table(Tables::SCREEN_LINES)->orderBy('id')->get()->groupBy('ticket_id');

        return new KitchenScreenView(DB::table(Tables::SCREEN_TICKETS)
            ->orderBy('ticket_id')
            ->get()
            ->map(fn (object $ticket): KitchenScreenTicket => new KitchenScreenTicket(
                new KitchenTicketId((string) $ticket->ticket_id),
                new PartyRef((string) $ticket->party_ref),
                new TableLabel((string) $ticket->table_label),
                (bool) $ticket->done,
                $lines->get($ticket->ticket_id, collect())
                    ->map(fn (object $line): KitchenScreenLine => $this->line($line))
                    ->values()
                    ->all(),
            ))
            ->all());
    }

    private function line(object $line): KitchenScreenLine
    {
        $allergens = $line->allergens;
        if (is_string($allergens)) {
            $allergens = json_decode($allergens, true, 512, JSON_THROW_ON_ERROR);
        }

        return new KitchenScreenLine(
            new LineId((string) $line->line_id),
            new DishName((string) $line->dish_name),
            (string) $line->station,
            (int) $line->cooking_minutes,
            (int) $line->quantity,
            (string) $line->course,
            is_array($allergens) ? array_values($allergens) : [],
            (string) $line->status,
        );
    }
}
