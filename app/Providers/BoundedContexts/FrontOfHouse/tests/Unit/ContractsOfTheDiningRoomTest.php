<?php

use App\Providers\BoundedContexts\FrontOfHouse\Application\Integration\BillRequestedV1;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Integration\DishServedV1;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Integration\TableOrderCancelledV1;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Integration\TableOrderPlacedV1;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Integration\TableOrderRevisedV1;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishKind;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\BillRequested;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\DishServed;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderCancelled;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderPlaced;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderRevised;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TicketId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;

/**
 * @return list<OrderLine>
 */
function aSteakLine(): array
{
    return [new OrderLine(new LineId('order-1-1'), new MenuItemId('steak-frites'), new DishName('Steak frites'), 1, 'main', 2200, DishKind::FOOD, ['gluten'])];
}

function publishedAs(string $contract): string
{
    $published = (new ReflectionClass($contract))->getAttributes(PublishedAs::class)[0]->newInstance();

    return "{$published->type} v{$published->version}";
}

describe('what the dining room tells the others', function () {
    test('an order placed: the table, and what the kitchen needs of the lines, without the price', function () {
        $contract = TableOrderPlacedV1::from(new TableOrderPlaced(new TableOrderId('order-1'), new TableId('table-4'), new VisitId('visit-1'), new TableLabel('Window 4'), aSteakLine()));

        expect(publishedAs(TableOrderPlacedV1::class))->toBe('front_of_house.table_order_placed v1')
            ->and($contract->toPayload())->toBe([
                'tableOrderId' => 'order-1',
                'tableId' => 'table-4',
                'visitId' => 'visit-1',
                'tableLabel' => 'Window 4',
                'lines' => [['lineId' => 'order-1-1', 'name' => 'Steak frites', 'quantity' => 1, 'course' => 'main', 'allergens' => ['gluten']]],
            ])
            ->and(TableOrderPlacedV1::fromPayload($contract->toPayload()))->toEqual($contract);
    });

    test('an order revised: which revision it is, and only the lines it adds', function () {
        $contract = TableOrderRevisedV1::from(new TableOrderRevised(new TableOrderId('order-1'), new TableId('table-4'), new TableLabel('Window 4'), 2, aSteakLine()));

        expect(publishedAs(TableOrderRevisedV1::class))->toBe('front_of_house.table_order_revised v1')
            ->and($contract->toPayload())->toBe([
                'tableOrderId' => 'order-1',
                'tableId' => 'table-4',
                'tableLabel' => 'Window 4',
                'revision' => 2,
                'lines' => [['lineId' => 'order-1-1', 'name' => 'Steak frites', 'quantity' => 1, 'course' => 'main', 'allergens' => ['gluten']]],
            ])
            ->and(TableOrderRevisedV1::fromPayload($contract->toPayload()))->toEqual($contract);
    });

    test('an order cancelled: why, and the lines that are abandoned', function () {
        $contract = TableOrderCancelledV1::from(new TableOrderCancelled(new TableOrderId('order-1'), new TableId('table-4'), 'The guests gave up.', [new LineId('order-1-2')]));

        expect(publishedAs(TableOrderCancelledV1::class))->toBe('front_of_house.table_order_cancelled v1')
            ->and($contract->toPayload())->toBe([
                'tableOrderId' => 'order-1',
                'tableId' => 'table-4',
                'reason' => 'The guests gave up.',
                'abandonedLines' => ['order-1-2'],
            ])
            ->and(TableOrderCancelledV1::fromPayload($contract->toPayload()))->toEqual($contract);
    });

    test('a bill requested: which table', function () {
        $contract = BillRequestedV1::from(new BillRequested(new TableId('table-4'), new VisitId('visit-1')));

        expect(publishedAs(BillRequestedV1::class))->toBe('front_of_house.bill_requested v1')
            ->and($contract->toPayload())->toBe(['tableId' => 'table-4', 'visitId' => 'visit-1'])
            ->and(BillRequestedV1::fromPayload($contract->toPayload()))->toEqual($contract);
    });

    test('a dish served: what the till bills, and where it comes from', function () {
        $contract = DishServedV1::from(new DishServed(new TableId('table-4'), new VisitId('visit-1'), new DishName('Green salad'), 2, 1100, DishKind::FOOD, new TicketId('ticket-1'), new LineId('line-1')));

        expect(publishedAs(DishServedV1::class))->toBe('front_of_house.dish_served v1')
            ->and($contract->toPayload())->toBe([
                'tableId' => 'table-4',
                'visitId' => 'visit-1',
                'tableOrderId' => 'ticket-1',
                'lineId' => 'line-1',
                'description' => 'Green salad',
                'quantity' => 2,
                'unitPriceCents' => 1100,
                'kind' => DishKind::FOOD,
                'sourceKey' => 'ticket-1:line-1',
            ])
            ->and(DishServedV1::fromPayload($contract->toPayload()))->toEqual($contract);
    });
});
