<?php

use App\Providers\BoundedContexts\Kitchen\Domain\Dish;
use App\Providers\BoundedContexts\Kitchen\Domain\DishId;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishEightySixed;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;

test('a dish can be declared 86 once', function () {
    $dish = Dish::restore(new DishId('dish-steak'), new DishName('Steak frites'), 'grill', 12, true);

    $dish->eightySix();

    expect($dish->isInStock())->toBeFalse();
    expect(fn () => $dish->eightySix())
        ->toThrow(KitchenException::class, 'Steak frites is already 86.');
});

test('a dish declared 86 records it, and nothing when it already was', function () {
    $dish = Dish::restore(new DishId('dish-steak'), new DishName('Steak frites'), 'grill', 12, true);

    $dish->eightySix();
    expect($dish->releaseEvents())->toEqual([new DishEightySixed(new DishId('dish-steak'), new DishName('Steak frites'))]);

    try {
        $dish->eightySix();
    } catch (KitchenException) {
    }

    expect($dish->releaseEvents())->toBe([]);
});
