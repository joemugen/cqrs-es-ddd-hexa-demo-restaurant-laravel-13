<?php

use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId as TicketToCancelId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Consumed\FrontOfHouseTableOrderClosed;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Consumed\FrontOfHouseTableOrderPlaced;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Consumed\FrontOfHouseTableOrderRevised;

function anOrderPayload(): array
{
    return [
        'tableOrderId' => 'order-1',
        'tableId' => 'table-4',
        'tableLabel' => 'Window 4',
        'lines' => [['lineId' => 'order-1-1', 'name' => 'Steak frites', 'quantity' => 1, 'course' => 'main', 'allergens' => ['gluten']]],
    ];
}

describe('what the kitchen reads of the dining room', function () {
    test('an order placed: the ticket it is for, the party and the lines in the words of the kitchen', function () {
        $message = FrontOfHouseTableOrderPlaced::fromPayload(anOrderPayload());

        expect($message->ticketId)->toEqual(new KitchenTicketId('order-1'))
            ->and($message->partyRef)->toEqual(new PartyRef('table-4'))
            ->and($message->tableLabel)->toEqual(new TableLabel('Window 4'))
            ->and($message->lines)->toEqual([new OrderedLine(new LineId('order-1-1'), new DishName('Steak frites'), 1, 'main', ['gluten'])]);
    });

    test('an order revised: the same words, for the lines it adds', function () {
        $message = FrontOfHouseTableOrderRevised::fromPayload(anOrderPayload());

        expect($message->ticketId)->toEqual(new KitchenTicketId('order-1'))
            ->and($message->lines)->toHaveCount(1);
    });

    test('an order closed: only which ticket to stop, which has the id of the order', function () {
        expect(FrontOfHouseTableOrderClosed::fromPayload(['tableOrderId' => 'order-1', 'tableId' => 'table-4'])->ticketId)->toEqual(new TicketToCancelId('order-1'));
    });
});
