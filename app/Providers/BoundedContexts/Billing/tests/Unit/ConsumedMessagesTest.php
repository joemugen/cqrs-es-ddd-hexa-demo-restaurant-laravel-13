<?php

use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\BoundedContexts\Billing\Infrastructure\Consumed\FrontOfHouseBillRequested;
use App\Providers\BoundedContexts\Billing\Infrastructure\Consumed\FrontOfHouseDishServed;

describe('what the till reads of the dining room', function () {
    test('a dish served: the cover, and the line to bill', function () {
        $message = FrontOfHouseDishServed::fromPayload([
            'tableId' => 'table-4',
            'description' => 'Green salad',
            'quantity' => 2,
            'unitPriceCents' => 1100,
            'kind' => 'food',
            'sourceKey' => 'ticket-1:line-1',
        ]);

        expect($message->coverRef)->toEqual(new CoverRef('table-4'))
            ->and($message->dish)->toEqual(new ServedDish(new SourceKey('ticket-1:line-1'), 'Green salad', 2, 1100, 'food'));
    });

    test('a bill requested: whose cover', function () {
        expect(FrontOfHouseBillRequested::fromPayload(['tableId' => 'table-4'])->coverRef)->toEqual(new CoverRef('table-4'));
    });
});
