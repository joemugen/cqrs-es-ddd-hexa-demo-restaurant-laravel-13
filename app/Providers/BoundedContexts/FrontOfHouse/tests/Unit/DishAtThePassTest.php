<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TicketId;

test('a dish at the pass can be served once', function () {
    $dish = new DishAtThePass(new DishAtThePassId('1'), new TableId('table-4'), new DishName('Green salad'), 1, new TicketId('ticket-1'), new LineId('line-1'), false);

    $dish->serve();

    expect($dish->isServed())->toBeTrue();
    expect(fn () => $dish->serve())
        ->toThrow(FrontOfHouseException::class, 'That dish has already left the pass.');
});
