<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderPlaced;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderRevised;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Menu;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItem;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\ObsoleteRevision;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;

function sampleMenu(): Menu
{
    $salad = MenuItem::restore(new MenuItemId('green-salad'), new DishName('Green salad'), 'Leaves, herbs, lemon.', 1100, [], true, 'food');
    $steak = MenuItem::restore(new MenuItemId('steak-frites'), new DishName('Steak frites'), 'Grilled beef, fries.', 2200, ['gluten'], false, 'food');

    return new Menu([
        (string) $salad->id() => $salad,
        (string) $steak->id() => $steak,
    ]);
}

function seatedOrder(): TableOrder
{
    return TableOrder::place(
        new TableOrderId('order-1'),
        Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2, new VisitId('visit-1')),
        sampleMenu(),
        [
            new RequestedLine(new MenuItemId('green-salad'), 1, 'starter'),
            new RequestedLine(new MenuItemId('green-salad'), 2, 'starter'),
        ],
    );
}

describe('TableOrder', function () {
    test('a seated table can place an order', function () {
        $seated = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2, new VisitId('visit-1'));

        $order = TableOrder::place(new TableOrderId('order-1'), $seated, sampleMenu(), [
            new RequestedLine(new MenuItemId('green-salad'), 1, 'starter'),
        ]);

        expect($order->forTheKitchen())->toBe([
            [
                'lineId' => 'order-1-1',
                'name' => 'Green salad',
                'quantity' => 1,
                'course' => 'starter',
                'allergens' => [],
            ],
        ])->and($order->forTheKitchen()[0])->not->toHaveKey('unitPriceCents');
    });

    test('belongs to the visit of the guests who ordered', function () {
        expect(seatedOrder()->visitId())->toEqual(new VisitId('visit-1'));
    });

    test('a table without guests cannot order', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 0);

        expect(fn () => TableOrder::place(new TableOrderId('order-1'), $table, sampleMenu(), [
            new RequestedLine(new MenuItemId('green-salad'), 1, 'starter'),
        ]))->toThrow(FrontOfHouseException::class, 'No order for a table without seated guests.');
    });

    test('an empty order is refused', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2, new VisitId('visit-1'));

        expect(fn () => TableOrder::place(new TableOrderId('order-1'), $table, sampleMenu(), []))
            ->toThrow(FrontOfHouseException::class, 'An order needs at least one item.');
    });

    test('an unavailable item is refused', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2, new VisitId('visit-1'));

        expect(fn () => TableOrder::place(new TableOrderId('order-1'), $table, sampleMenu(), [
            new RequestedLine(new MenuItemId('steak-frites'), 1, 'main'),
        ]))->toThrow(FrontOfHouseException::class, 'Steak frites is unavailable.');
    });

    test('marking a menu item unavailable keeps it off the next order', function () {
        $item = MenuItem::restore(new MenuItemId('green-salad'), new DishName('Green salad'), 'Leaves.', 1100, [], true, 'food');
        $item->markUnavailable();

        expect($item->isAvailable())->toBeFalse();
    });

    test('records that it was placed, with its lines and the table it is for', function () {
        $order = seatedOrder();

        $placed = $order->releaseEvents();

        expect($placed)->toHaveCount(1)
            ->and($placed[0])->toBeInstanceOf(TableOrderPlaced::class)
            ->and((string) $placed[0]->tableOrderId)->toBe('order-1')
            ->and((string) $placed[0]->tableId)->toBe('table-4')
            ->and((string) $placed[0]->visitId)->toBe('visit-1')
            ->and((string) $placed[0]->tableLabel)->toBe('Window 4')
            ->and($placed[0]->lines)->toHaveCount(2);
    });

    describe('a line of the order', function () {
        test('is found by its id, with the price it was ordered at', function () {
            $line = seatedOrder()->line(new LineId('order-1-2'));

            expect($line->quantity)->toBe(2)
                ->and($line->unitPriceCents)->toBe(1100);
        });

        test('is unknown when the order never had it', function () {
            expect(fn () => seatedOrder()->line(new LineId('order-1-9')))
                ->toThrow(FrontOfHouseException::class, 'That line is not on this order.');
        });
    });

    describe('when revised', function () {
        function windowTable(): Table
        {
            return Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2, new VisitId('visit-1'));
        }

        test('the added lines go on after the lines already placed, and are recorded', function () {
            $order = seatedOrder();
            $order->releaseEvents();

            $order->revise(windowTable(), sampleMenu(), [new RequestedLine(new MenuItemId('green-salad'), 1, 'main')]);

            $revised = $order->releaseEvents();

            expect($revised)->toHaveCount(1)
                ->and($revised[0])->toBeInstanceOf(TableOrderRevised::class)
                ->and(array_map(fn ($line): array => $line->forTheKitchen(), $revised[0]->addedLines))->toBe([
                    [
                        'lineId' => 'order-1-3',
                        'name' => 'Green salad',
                        'quantity' => 1,
                        'course' => 'main',
                        'allergens' => [],
                    ],
                ])
                ->and($order->lines())->toHaveCount(3);
        });

        test('is obsolete for a table that hosts other guests than the ones who ordered', function () {
            $order = seatedOrder();
            $nextGuests = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2, new VisitId('visit-2'));

            expect(fn () => $order->revise($nextGuests, sampleMenu(), [new RequestedLine(new MenuItemId('green-salad'), 1, 'main')]))
                ->toThrow(ObsoleteRevision::class, 'The revision of the order is obsolete: the guests already paid and left.');
        });

        test('is obsolete for a table the guests left', function () {
            $empty = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 0);

            expect(fn () => seatedOrder()->revise($empty, sampleMenu(), [new RequestedLine(new MenuItemId('green-salad'), 1, 'main')]))
                ->toThrow(ObsoleteRevision::class);
        });

        test('an empty revision is refused', function () {
            expect(fn () => seatedOrder()->revise(windowTable(), sampleMenu(), []))
                ->toThrow(FrontOfHouseException::class, 'A revision needs at least one item.');
        });

        test('an unavailable item is refused, as it is for an order, and nothing is recorded', function () {
            $order = seatedOrder();
            $order->releaseEvents();

            expect(fn () => $order->revise(windowTable(), sampleMenu(), [new RequestedLine(new MenuItemId('steak-frites'), 1, 'main')]))
                ->toThrow(FrontOfHouseException::class, 'Steak frites is unavailable.')
                ->and($order->releaseEvents())->toBe([])
                ->and($order->lines())->toHaveCount(2);
        });
    });
});
