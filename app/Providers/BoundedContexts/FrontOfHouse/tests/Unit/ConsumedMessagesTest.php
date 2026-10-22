<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\UnavailableLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\BillingBillPaid;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\FrontOfHouseTableOrderRevised;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenDishEightySixed;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenDishesAdded;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenLinesUnavailable;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenTicketOpened;

describe('what the dining room reads of the others', function () {
    test('a ticket opened: only which order, the rest is the business of the kitchen', function () {
        $message = KitchenTicketOpened::fromPayload(['tableOrderId' => 'order-1', 'lines' => [['lineId' => 'order-1-1', 'name' => 'Green salad', 'quantity' => 1, 'course' => 'starter']]]);

        expect($message->tableOrderId)->toEqual(new TableOrderId('order-1'));
    });

    test('dishes added: which order', function () {
        expect(KitchenDishesAdded::fromPayload(['tableOrderId' => 'order-1', 'lines' => []])->tableOrderId)->toEqual(new TableOrderId('order-1'));
    });

    test('lines unavailable: the order, the position it answers and the lines in the words of the room', function () {
        $message = KitchenLinesUnavailable::fromPayload([
            'tableOrderId' => 'order-1',
            'tableId' => 'table-4',
            'orderPosition' => 12,
            'lines' => [['lineId' => 'order-1-2', 'name' => 'Steak frites', 'reason' => 'Steak frites is 86.']],
        ]);

        expect($message->tableOrderId)->toEqual(new TableOrderId('order-1'))
            ->and($message->orderPosition)->toBe(12)
            ->and($message->lines)->toEqual([new UnavailableLine(new LineId('order-1-2'), new DishName('Steak frites'), 'Steak frites is 86.')]);
    });

    test('a dish eighty-sixed: its name', function () {
        expect(KitchenDishEightySixed::fromPayload(['dishName' => 'Steak frites'])->dishName)->toEqual(new DishName('Steak frites'));
    });

    test('an order revised, in its own words: which order and which revision', function () {
        $message = FrontOfHouseTableOrderRevised::fromPayload(['tableOrderId' => 'order-1', 'tableId' => 'table-4', 'tableLabel' => 'Window 4', 'revision' => 2, 'lines' => []]);

        expect($message->tableOrderId)->toEqual(new TableOrderId('order-1'))
            ->and($message->revision)->toBe(2);
    });

    test('a bill paid: the cover of the till is a visit of the room', function () {
        expect(BillingBillPaid::fromPayload(['coverRef' => 'visit-1', 'billId' => 'bill-1'])->visitId)->toEqual(new VisitId('visit-1'));
    });
});
