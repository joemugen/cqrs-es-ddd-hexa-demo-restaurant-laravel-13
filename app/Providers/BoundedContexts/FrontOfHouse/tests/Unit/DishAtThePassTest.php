<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishKind;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\DishServed;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItem;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TicketId;

function aSaladAtThePass(): DishAtThePass
{
    return new DishAtThePass(new DishAtThePassId('1'), new TableId('table-4'), new DishName('Green salad'), 2, new TicketId('ticket-1'), new LineId('line-1'), false);
}

function theSaladOnTheMenu(): MenuItem
{
    return MenuItem::restore(new MenuItemId('green-salad'), new DishName('Green salad'), 'Leaves.', 1100, [], true, DishKind::FOOD);
}

describe('a dish at the pass', function () {
    test('can be served once', function () {
        $dish = aSaladAtThePass();

        $dish->serve(theSaladOnTheMenu());

        expect($dish->isServed())->toBeTrue();
        expect(fn () => $dish->serve(theSaladOnTheMenu()))
            ->toThrow(FrontOfHouseException::class, 'That dish has already left the pass.');
    });

    test('records that it was served, with the price and the kind the till will need', function () {
        $dish = aSaladAtThePass();

        $dish->serve(theSaladOnTheMenu());

        expect($dish->releaseEvents())->toEqual([
            new DishServed(new TableId('table-4'), new DishName('Green salad'), 2, 1100, DishKind::FOOD, new TicketId('ticket-1'), new LineId('line-1')),
        ]);
    });

    test('records nothing when it was already served', function () {
        $dish = aSaladAtThePass();
        $dish->serve(theSaladOnTheMenu());
        $dish->releaseEvents();

        try {
            $dish->serve(theSaladOnTheMenu());
        } catch (FrontOfHouseException) {
        }

        expect($dish->releaseEvents())->toBe([]);
    });
});
