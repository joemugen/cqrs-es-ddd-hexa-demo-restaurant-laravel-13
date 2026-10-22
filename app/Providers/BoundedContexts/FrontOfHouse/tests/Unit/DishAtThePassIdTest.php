<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TicketId;

describe('the id of a dish at the pass', function () {
    test('comes from the ticket and the line, so putting the same dish twice finds the first one', function () {
        expect(DishAtThePassId::forLine(new TicketId('order-1'), new LineId('order-1-2')))
            ->toEqual(DishAtThePassId::forLine(new TicketId('order-1'), new LineId('order-1-2')));
    });

    test('is another one for another line', function () {
        expect(DishAtThePassId::forLine(new TicketId('order-1'), new LineId('order-1-2')))
            ->not->toEqual(DishAtThePassId::forLine(new TicketId('order-1'), new LineId('order-1-3')));
    });
});
