<?php

use App\Providers\BoundedContexts\Kitchen\Domain\Dish;
use App\Providers\BoundedContexts\Kitchen\Domain\DishId;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\LinesUnavailable;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderAssessment;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\BoundedContexts\Kitchen\Domain\UnavailableLine;

const ORDER_AT = 10;

function theSteak(bool $inStock = true, ?int $eightySixedAt = null): Dish
{
    return Dish::restore(new DishId('dish-steak'), new DishName('Steak frites'), 'grill', 12, $inStock, $eightySixedAt);
}

function theSalad(): Dish
{
    return Dish::restore(new DishId('dish-salad'), new DishName('Green salad'), 'garnish', 4, true);
}

function steakLine(string $id = 'order-1-2'): OrderedLine
{
    return new OrderedLine(new LineId($id), new DishName('Steak frites'), 1, 'main', ['gluten']);
}

function saladLine(): OrderedLine
{
    return new OrderedLine(new LineId('order-1-1'), new DishName('Green salad'), 1, 'starter', []);
}

/**
 * @param  list<OrderedLine>  $lines
 * @param  list<Dish>  $dishes
 */
function assessed(array $lines, array $dishes)
{
    return OrderAssessment::of(new KitchenTicketId('order-1'), new PartyRef('table-4'), $lines, $dishes, ORDER_AT);
}

describe('a dish', function () {
    test('in stock is available whatever the position', function () {
        expect(theSteak()->isAvailableAt(ORDER_AT))->toBeTrue();
    });

    test('declared 86 after the order was available when the order was placed', function () {
        expect(theSteak(false, ORDER_AT + 1)->isAvailableAt(ORDER_AT))->toBeTrue();
    });

    test('declared 86 before the order is not available to it', function () {
        expect(theSteak(false, ORDER_AT - 1)->isAvailableAt(ORDER_AT))->toBeFalse();
    });

    test('out of stock with no recorded position counts as 86 for every order', function () {
        expect(theSteak(false)->isAvailableAt(ORDER_AT))->toBeFalse();
    });

    test('turns an ordered line into a line of the ticket, with its station and its cooking time', function () {
        expect(theSteak()->ticketLineFor(steakLine()))->toEqual(
            new TicketLine(new LineId('order-1-2'), new DishName('Steak frites'), 'grill', 12, 1, 'main', ['gluten']),
        );
    });
});

describe('the assessment of an order', function () {
    test('keeps every line that can be cooked, and tells nothing when there is nothing to tell', function () {
        $assessed = assessed([saladLine(), steakLine()], [theSalad(), theSteak()]);

        expect($assessed->cookable)->toHaveCount(2)
            ->and($assessed->unavailable)->toBeNull();
    });

    test('judges the dishes where the order sits in the log: a 86 declared after it does not count', function () {
        $assessed = assessed([steakLine()], [theSteak(false, ORDER_AT + 1)]);

        expect($assessed->cookable)->toHaveCount(1)
            ->and($assessed->unavailable)->toBeNull();
    });

    test('sets aside the line of a dish that was 86 before the order, with the reason, as a fact', function () {
        $assessed = assessed([saladLine(), steakLine()], [theSalad(), theSteak(false, ORDER_AT - 1)]);

        expect(array_map(fn (TicketLine $line): string => (string) $line->dishName, $assessed->cookable))->toBe(['Green salad'])
            ->and($assessed->unavailable)->toEqual(new LinesUnavailable(
                new KitchenTicketId('order-1'),
                new PartyRef('table-4'),
                ORDER_AT,
                [new UnavailableLine(new LineId('order-1-2'), new DishName('Steak frites'), 'Steak frites is 86.')],
            ));
    });

    test('has nothing to cook when every dish is 86, and says so', function () {
        $assessed = assessed([steakLine()], [theSteak(false)]);

        expect($assessed->cookable)->toBe([])
            ->and($assessed->unavailable->lines)->toHaveCount(1);
    });

    test('sets aside a dish the kitchen does not know, and does not hide the others', function () {
        $dragon = new OrderedLine(new LineId('order-1-3'), new DishName('Dragon steak'), 1, 'main', []);

        $assessed = assessed([saladLine(), $dragon], [theSalad()]);

        expect($assessed->cookable)->toHaveCount(1)
            ->and($assessed->unavailable->lines[0]->reason)->toBe('The kitchen has no dish called Dragon steak.');
    });
});
