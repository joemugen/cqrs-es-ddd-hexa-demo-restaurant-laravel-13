<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure;

use App\Providers\BoundedContexts\Kitchen\Application\DishCatalog;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\LinesAvailability;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\BoundedContexts\Kitchen\Domain\UnavailableLine;
use Illuminate\Support\Facades\DB;

final class DatabaseDishCatalog implements DishCatalog
{
    public function linesFor(array $ordered, int $asOfPosition): LinesAvailability
    {
        $available = [];
        $unavailable = [];

        foreach ($ordered as $line) {
            $dish = DB::table(Tables::DISHES)->where('name', (string) $line->name)->first();

            if ($dish === null) {
                $unavailable[] = new UnavailableLine($line->lineId, $line->name, "The kitchen has no dish called {$line->name}.");
            } elseif (! $dish->in_stock && $this->wasEightySixedBefore($dish, $asOfPosition)) {
                $unavailable[] = new UnavailableLine($line->lineId, $line->name, "{$dish->name} is 86.");
            } else {
                $available[] = $this->ticketLine($line, $dish);
            }
        }

        return new LinesAvailability($available, $unavailable);
    }

    private function ticketLine(OrderedLine $line, object $dish): TicketLine
    {
        return new TicketLine(
            lineId: $line->lineId,
            dishName: new DishName((string) $dish->name),
            station: (string) $dish->station,
            cookingMinutes: (int) $dish->cooking_minutes,
            quantity: $line->quantity,
            course: $line->course,
            allergens: array_values($line->allergens),
        );
    }

    /**
     * A dish out of stock with no recorded position predates the position and counts as 86 for every order.
     */
    private function wasEightySixedBefore(object $dish, int $asOfPosition): bool
    {
        return $dish->eighty_sixed_position === null || (int) $dish->eighty_sixed_position < $asOfPosition;
    }
}
