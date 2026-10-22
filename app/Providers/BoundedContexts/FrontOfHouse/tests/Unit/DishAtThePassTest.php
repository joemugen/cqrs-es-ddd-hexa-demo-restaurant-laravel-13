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
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;

function aSaladAtThePass(): DishAtThePass
{
    return new DishAtThePass(new DishAtThePassId('1'), new TableId('table-4'), new DishName('Green salad'), 2, new TableOrderId('order-1'), new LineId('line-1'), false);
}

function theSaladAsOrdered(): OrderLine
{
    return new OrderLine(new LineId('line-1'), new MenuItemId('green-salad'), new DishName('Green salad'), 2, 'starter', 1100, DishKind::FOOD, []);
}

describe('a dish at the pass', function () {
    test('belongs to a line of an order of the room, which the kitchen calls a ticket', function () {
        expect(aSaladAtThePass()->tableOrderId())->toEqual(new TableOrderId('order-1'));
    });

    test('can be served once', function () {
        $dish = aSaladAtThePass();

        $dish->serve(theSaladAsOrdered(), new VisitId('visit-1'));

        expect($dish->isServed())->toBeTrue();
        expect(fn () => $dish->serve(theSaladAsOrdered(), new VisitId('visit-1')))
            ->toThrow(FrontOfHouseException::class, 'That dish has already left the pass.');
    });

    test('records that it was served, with the price and the kind of the line as it was ordered', function () {
        $dish = aSaladAtThePass();

        $dish->serve(theSaladAsOrdered(), new VisitId('visit-1'));

        expect($dish->releaseEvents())->toEqual([
            new DishServed(new TableId('table-4'), new VisitId('visit-1'), new DishName('Green salad'), 2, 1100, DishKind::FOOD, new TableOrderId('order-1'), new LineId('line-1')),
        ]);
    });

    test('records nothing when it was already served', function () {
        $dish = aSaladAtThePass();
        $dish->serve(theSaladAsOrdered(), new VisitId('visit-1'));
        $dish->releaseEvents();

        try {
            $dish->serve(theSaladAsOrdered(), new VisitId('visit-1'));
        } catch (FrontOfHouseException) {
        }

        expect($dish->releaseEvents())->toBe([]);
    });
});
