<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Menu;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItem;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;

function sampleMenu(): Menu
{
    $salad = MenuItem::restore(new MenuItemId('green-salad'), new DishName('Green salad'), 'Leaves, herbs, lemon.', 1100, [], true, 'food');
    $steak = MenuItem::restore(new MenuItemId('steak-frites'), new DishName('Steak frites'), 'Grilled beef, fries.', 2200, ['gluten'], false, 'food');

    return new Menu([
        (string) $salad->id() => $salad,
        (string) $steak->id() => $steak,
    ]);
}

describe('TableOrder', function () {
    test('a seated table can place an order', function () {
        $seated = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2);

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

    test('a table without guests cannot order', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 0);

        expect(fn () => TableOrder::place(new TableOrderId('order-1'), $table, sampleMenu(), [
            new RequestedLine(new MenuItemId('green-salad'), 1, 'starter'),
        ]))->toThrow(FrontOfHouseException::class, 'No order for a table without seated guests.');
    });

    test('an empty order is refused', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2);

        expect(fn () => TableOrder::place(new TableOrderId('order-1'), $table, sampleMenu(), []))
            ->toThrow(FrontOfHouseException::class, 'An order needs at least one item.');
    });

    test('an unavailable item is refused', function () {
        $table = Table::restore(new TableId('table-4'), new TableLabel('Window 4'), 2);

        expect(fn () => TableOrder::place(new TableOrderId('order-1'), $table, sampleMenu(), [
            new RequestedLine(new MenuItemId('steak-frites'), 1, 'main'),
        ]))->toThrow(FrontOfHouseException::class, 'Steak frites is unavailable.');
    });

    test('marking a menu item unavailable keeps it off the next order', function () {
        $item = MenuItem::restore(new MenuItemId('green-salad'), new DishName('Green salad'), 'Leaves.', 1100, [], true, 'food');
        $item->markUnavailable();

        expect($item->isAvailable())->toBeFalse();
    });
});
