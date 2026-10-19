<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure;

use App\Providers\BoundedContexts\Kitchen\Application\DishCatalog;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use Illuminate\Support\Facades\DB;

final class DatabaseDishCatalog implements DishCatalog
{
    public function linesFor(array $ordered, int $asOfPosition): array
    {
        return array_map(fn (OrderedLine $line): TicketLine => $this->ticketLine($line, $asOfPosition), $ordered);
    }

    private function ticketLine(OrderedLine $line, int $asOfPosition): TicketLine
    {
        $dish = DB::table(Tables::DISHES)->where('name', (string) $line->name)->first();

        if ($dish === null) {
            throw new KitchenException("The kitchen has no dish called {$line->name}.");
        }

        if (! $dish->in_stock && $this->wasEightySixedBefore($dish, $asOfPosition)) {
            throw new KitchenException("{$dish->name} is 86.");
        }

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
