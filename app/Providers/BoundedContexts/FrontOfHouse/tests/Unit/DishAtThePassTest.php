<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;

test('a dish at the pass can be served once', function () {
    $dish = new DishAtThePass('1', 'table-4', 'Green salad', 1, 'ticket-1', 'line-1', false);

    $dish->serve();

    expect($dish->isServed())->toBeTrue();
    expect(fn () => $dish->serve())
        ->toThrow(FrontOfHouseException::class, 'That dish has already left the pass.');
});
