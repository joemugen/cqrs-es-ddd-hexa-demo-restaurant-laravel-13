<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

use App\Providers\BoundedContexts\Kitchen\Domain\Event\LinesUnavailable;

/**
 * The kitchen reads an order against what it has: the lines it can cook go to the ticket, and for the others it declares
 * a fact. The judgement is the domain's: an order is judged where it sits in the log, not against the stock of the moment.
 */
final class OrderAssessment
{
    /**
     * @param  list<OrderedLine>  $ordered
     * @param  list<Dish>  $dishes  the dishes of the kitchen, by which the lines are found
     * @param  int  $orderPosition  where the order, or its revision, sits in the log
     */
    public static function of(KitchenTicketId $ticketId, PartyRef $partyRef, array $ordered, array $dishes, int $orderPosition): AssessedOrder
    {
        $cookable = [];
        $unavailable = [];

        foreach ($ordered as $line) {
            $dish = self::dishCalled($line->name, $dishes);

            if ($dish === null) {
                $unavailable[] = new UnavailableLine($line->lineId, $line->name, "The kitchen has no dish called {$line->name}.");
            } elseif (! $dish->isAvailableAt($orderPosition)) {
                $unavailable[] = new UnavailableLine($line->lineId, $line->name, "{$line->name} is 86.");
            } else {
                $cookable[] = $dish->ticketLineFor($line);
            }
        }

        return new AssessedOrder(
            $cookable,
            $unavailable === [] ? null : new LinesUnavailable($ticketId, $partyRef, $orderPosition, $unavailable),
        );
    }

    /**
     * @param  list<Dish>  $dishes
     */
    private static function dishCalled(DishName $name, array $dishes): ?Dish
    {
        foreach ($dishes as $dish) {
            if ($dish->name()->equals($name)) {
                return $dish;
            }
        }

        return null;
    }
}
