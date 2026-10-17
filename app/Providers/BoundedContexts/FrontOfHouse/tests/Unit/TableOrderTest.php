<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Menu;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItem;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;

function sampleMenu(): Menu
{
    $salad = MenuItem::restore('green-salad', 'Green salad', 'Leaves, herbs, lemon.', 1100, [], true, 'food');
    $steak = MenuItem::restore('steak-frites', 'Steak frites', 'Grilled beef, fries.', 2200, ['gluten'], false, 'food');

    return new Menu([
        $salad->id() => $salad,
        $steak->id() => $steak,
    ]);
}

test('a seated table can place an order', function () {
    $seated = Table::restore('table-4', 'Window 4', 2);

    $order = TableOrder::place('order-1', $seated, sampleMenu(), [
        ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
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
    $table = Table::restore('table-4', 'Window 4', 0);

    expect(fn () => TableOrder::place('order-1', $table, sampleMenu(), [
        ['menuItemId' => 'green-salad', 'quantity' => 1, 'course' => 'starter'],
    ]))->toThrow(FrontOfHouseException::class, 'No order for a table without seated guests.');
});

test('an empty order is refused', function () {
    $table = Table::restore('table-4', 'Window 4', 2);

    expect(fn () => TableOrder::place('order-1', $table, sampleMenu(), []))
        ->toThrow(FrontOfHouseException::class, 'An order needs at least one item.');
});

test('an unavailable item is refused', function () {
    $table = Table::restore('table-4', 'Window 4', 2);

    expect(fn () => TableOrder::place('order-1', $table, sampleMenu(), [
        ['menuItemId' => 'steak-frites', 'quantity' => 1, 'course' => 'main'],
    ]))->toThrow(FrontOfHouseException::class, 'Steak frites is unavailable.');
});

test('marking a menu item unavailable keeps it off the next order', function () {
    $item = MenuItem::restore('green-salad', 'Green salad', 'Leaves.', 1100, [], true, 'food');
    $item->markUnavailable();

    expect($item->isAvailable())->toBeFalse();
});
