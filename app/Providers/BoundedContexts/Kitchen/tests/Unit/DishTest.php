<?php

use App\Providers\BoundedContexts\Kitchen\Domain\Dish;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;

test('a dish can be declared 86 once', function () {
    $dish = Dish::restore('dish-steak', 'Steak frites', 'grill', 12, true);

    $dish->eightySix();

    expect($dish->isInStock())->toBeFalse();
    expect(fn () => $dish->eightySix())
        ->toThrow(KitchenException::class, 'Steak frites is already 86.');
});
