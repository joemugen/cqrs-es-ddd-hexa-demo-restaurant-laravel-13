<?php

use App\Providers\BoundedContexts\Kitchen\Application\Integration\DishesAddedV1;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishesAdded;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;

function dishesAddedToTheTicketOfOrder(string $order): DishesAdded
{
    return new DishesAdded(new KitchenTicketId($order), [
        new TicketLine(new LineId("{$order}-3"), new DishName('Creme brulee'), 'pastry', 8, 1, 'dessert', ['dairy', 'eggs']),
    ]);
}

describe('the domain event DishesAdded', function () {
    test('is stored under a name of the ticket, not under the name of the contract', function () {
        expect(dishesAddedToTheTicketOfOrder('order-1')->storedAs())->toBe('ticket.dishes_added');
    });

    test('is stored in the format of the domain and read back as the same fact', function () {
        $event = dishesAddedToTheTicketOfOrder('order-1');

        expect($event->toStored())->toBe([
            'ticketId' => 'order-1',
            'lines' => [[
                'lineId' => 'order-1-3',
                'dishName' => 'Creme brulee',
                'station' => 'pastry',
                'cookingMinutes' => 8,
                'quantity' => 1,
                'course' => 'dessert',
                'allergens' => ['dairy', 'eggs'],
            ]],
        ])->and(DishesAdded::fromStored($event->toStored()))->toEqual($event);
    });
});

describe('the contract DishesAddedV1', function () {
    test('tells the other contexts about the order and what they need of the lines, nothing of the stations', function () {
        expect(DishesAddedV1::from(dishesAddedToTheTicketOfOrder('order-1'))->toPayload())->toBe([
            'tableOrderId' => 'order-1',
            'lines' => [['lineId' => 'order-1-3', 'name' => 'Creme brulee', 'quantity' => 1, 'course' => 'dessert']],
        ]);
    });

    test('is read back by a consumer from its payload', function () {
        $contract = DishesAddedV1::from(dishesAddedToTheTicketOfOrder('order-1'));

        expect(DishesAddedV1::fromPayload($contract->toPayload()))->toEqual($contract);
    });
});
