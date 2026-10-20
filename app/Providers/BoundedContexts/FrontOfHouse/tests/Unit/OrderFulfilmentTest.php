<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FulfilmentStatus;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;

function fulfilmentAwaitingTheKitchen(): OrderFulfilment
{
    return OrderFulfilment::begin(new TableOrderId('order-1'), new TableId('table-7'));
}

describe('an order fulfilment', function () {
    test('starts awaiting the kitchen', function () {
        $fulfilment = fulfilmentAwaitingTheKitchen();

        expect($fulfilment->status())->toBe(FulfilmentStatus::AWAITING_KITCHEN)
            ->and((string) $fulfilment->id())->toBe('order-1')
            ->and((string) $fulfilment->tableId())->toBe('table-7')
            ->and($fulfilment->reason())->toBeNull();
    });

    test('is confirmed once the kitchen opened a ticket', function () {
        $fulfilment = fulfilmentAwaitingTheKitchen();

        $fulfilment->confirm();

        expect($fulfilment->status())->toBe(FulfilmentStatus::CONFIRMED);
    });

    test('is cancelled, with the reason, when the kitchen refused it', function () {
        $fulfilment = fulfilmentAwaitingTheKitchen();

        $fulfilment->cancel('Steak frites is 86.');

        expect($fulfilment->status())->toBe(FulfilmentStatus::CANCELLED)
            ->and($fulfilment->reason())->toBe('Steak frites is 86.');
    });

    test('cannot be cancelled once the kitchen confirmed it', function () {
        $fulfilment = fulfilmentAwaitingTheKitchen();
        $fulfilment->confirm();

        expect(fn () => $fulfilment->cancel('Too late.'))
            ->toThrow(FrontOfHouseException::class, 'A confirmed order cannot be cancelled.');
    });

    test('cannot be confirmed once it was cancelled', function () {
        $fulfilment = fulfilmentAwaitingTheKitchen();
        $fulfilment->cancel('Steak frites is 86.');

        expect(fn () => $fulfilment->confirm())
            ->toThrow(FrontOfHouseException::class, 'A cancelled order cannot be confirmed.');
    });

    test('needs a reason to be cancelled', function () {
        expect(fn () => fulfilmentAwaitingTheKitchen()->cancel('  '))
            ->toThrow(FrontOfHouseException::class, 'A cancellation needs a reason.');
    });

    test('can be restored in any state', function () {
        $fulfilment = OrderFulfilment::restore(new TableOrderId('order-1'), new TableId('table-7'), FulfilmentStatus::CANCELLED, 'Steak frites is 86.');

        expect($fulfilment->status())->toBe(FulfilmentStatus::CANCELLED)
            ->and($fulfilment->reason())->toBe('Steak frites is 86.');
    });
});
