<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishKind;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\DishServed;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TicketId;

function aSaladAtThePass(): DishAtThePass
{
    return new DishAtThePass(new DishAtThePassId('1'), new TableId('table-4'), new DishName('Green salad'), 2, new TicketId('ticket-1'), new LineId('line-1'), false);
}

function theSaladAsOrdered(): OrderLine
{
    return new OrderLine(new LineId('line-1'), new MenuItemId('green-salad'), new DishName('Green salad'), 2, 'starter', 1100, DishKind::FOOD, []);
}

describe('a dish at the pass', function () {
    test('can be served once', function () {
        $dish = aSaladAtThePass();

        $dish->serve(theSaladAsOrdered());

        expect($dish->isServed())->toBeTrue();
        expect(fn () => $dish->serve(theSaladAsOrdered()))
            ->toThrow(FrontOfHouseException::class, 'That dish has already left the pass.');
    });

    test('records that it was served, with the price and the kind of the line as it was ordered', function () {
        $dish = aSaladAtThePass();

        $dish->serve(theSaladAsOrdered());

        expect($dish->releaseEvents())->toEqual([
            new DishServed(new TableId('table-4'), new DishName('Green salad'), 2, 1100, DishKind::FOOD, new TicketId('ticket-1'), new LineId('line-1')),
        ]);
    });

    test('records nothing when it was already served', function () {
        $dish = aSaladAtThePass();
        $dish->serve(theSaladAsOrdered());
        $dish->releaseEvents();

        try {
            $dish->serve(theSaladAsOrdered());
        } catch (FrontOfHouseException) {
        }

        expect($dish->releaseEvents())->toBe([]);
    });
});
