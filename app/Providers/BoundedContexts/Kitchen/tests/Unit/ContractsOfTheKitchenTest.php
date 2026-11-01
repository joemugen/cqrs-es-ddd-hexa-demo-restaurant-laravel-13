<?php

use App\Providers\BoundedContexts\Kitchen\Application\Integration\DishEightySixedContract;
use App\Providers\BoundedContexts\Kitchen\Application\Integration\DishReadyContract;
use App\Providers\BoundedContexts\Kitchen\Application\Integration\LinesUnavailableContract;
use App\Providers\BoundedContexts\Kitchen\Application\Integration\TicketOpenedContract;
use App\Providers\BoundedContexts\Kitchen\Domain\DishId;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishEightySixed;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishReady;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\LinesUnavailable;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\TicketOpened;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\BoundedContexts\Kitchen\Domain\UnavailableLine;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;

function kitchenPublishedAs(string $contract): string
{
    $published = (new ReflectionClass($contract))->getAttributes(PublishedAs::class)[0]->newInstance();

    return "{$published->type} v{$published->version}";
}

describe('what the kitchen tells the others', function () {
    test('a ticket opened: which order, and what the others need of the lines', function () {
        $contract = TicketOpenedContract::from(new TicketOpened(
            new KitchenTicketId('order-1'),
            new PartyRef('table-4'),
            new TableLabel('Window 4'),
            [new TicketLine(new LineId('order-1-1'), new DishName('Steak frites'), 'grill', 12, 1, 'main', ['gluten'])],
        ));

        expect(kitchenPublishedAs(TicketOpenedContract::class))->toBe('kitchen.ticket_opened v1')
            ->and($contract->toPayload())->toBe([
                'tableOrderId' => 'order-1',
                'lines' => [['lineId' => 'order-1-1', 'name' => 'Steak frites', 'quantity' => 1, 'course' => 'main']],
            ])
            ->and(TicketOpenedContract::fromPayload($contract->toPayload()))->toEqual($contract);
    });

    test('a dish ready: where it goes and what it is, for the pass', function () {
        $contract = DishReadyContract::from(new DishReady(
            new KitchenTicketId('order-1'),
            new LineId('order-1-1'),
            new DishName('Green salad'),
            new PartyRef('table-4'),
            new TableLabel('Window 4'),
            2,
        ));

        expect(kitchenPublishedAs(DishReadyContract::class))->toBe('kitchen.dish_ready v1')
            ->and($contract->toPayload())->toBe([
                'tableOrderId' => 'order-1',
                'lineId' => 'order-1-1',
                'dishName' => 'Green salad',
                'tableId' => 'table-4',
                'tableLabel' => 'Window 4',
                'quantity' => 2,
            ])
            ->and(DishReadyContract::fromPayload($contract->toPayload()))->toEqual($contract);
    });

    test('a dish eighty-sixed: its name', function () {
        $contract = DishEightySixedContract::from(new DishEightySixed(new DishId('dish-steak'), new DishName('Steak frites')));

        expect(kitchenPublishedAs(DishEightySixedContract::class))->toBe('kitchen.dish_eighty_sixed v1')
            ->and($contract->toPayload())->toBe(['dishName' => 'Steak frites'])
            ->and(DishEightySixedContract::fromPayload($contract->toPayload()))->toEqual($contract);
    });

    test('lines unavailable: which order, which lines and why, and the position of the order it answers', function () {
        $contract = LinesUnavailableContract::from(new LinesUnavailable(
            new KitchenTicketId('order-1'),
            new PartyRef('table-4'),
            12,
            [new UnavailableLine(new LineId('order-1-2'), new DishName('Steak frites'), 'Steak frites is 86.')],
        ));

        expect(kitchenPublishedAs(LinesUnavailableContract::class))->toBe('kitchen.lines_unavailable v1')
            ->and($contract->toPayload())->toBe([
                'tableOrderId' => 'order-1',
                'tableId' => 'table-4',
                'orderPosition' => 12,
                'lines' => [['lineId' => 'order-1-2', 'name' => 'Steak frites', 'reason' => 'Steak frites is 86.']],
            ])
            ->and(LinesUnavailableContract::fromPayload($contract->toPayload()))->toEqual($contract);
    });
});
