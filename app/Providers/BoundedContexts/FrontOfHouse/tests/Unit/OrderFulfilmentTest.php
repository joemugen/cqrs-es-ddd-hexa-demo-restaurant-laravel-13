<?php

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FulfilmentStatus;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\ObsoleteRevision;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\UnavailableLine;

const ORDER_PLACED_AT = 10;

function fulfilmentAwaitingTheKitchen(): OrderFulfilment
{
    return OrderFulfilment::begin(new TableOrderId('order-1'), new TableId('table-7'), ORDER_PLACED_AT);
}

function steakUnavailable(): UnavailableLine
{
    return new UnavailableLine(new LineId('order-1-2'), new DishName('Steak frites'), 'Steak frites is 86.');
}

function fulfilmentAwaitingRevision(): OrderFulfilment
{
    $fulfilment = fulfilmentAwaitingTheKitchen();
    $fulfilment->confirm();
    $fulfilment->awaitRevision([steakUnavailable()], ORDER_PLACED_AT);

    return $fulfilment;
}

describe('an order fulfilment', function () {
    test('starts awaiting the kitchen', function () {
        $fulfilment = fulfilmentAwaitingTheKitchen();

        expect($fulfilment->status())->toBe(FulfilmentStatus::AWAITING_KITCHEN)
            ->and((string) $fulfilment->id())->toBe('order-1')
            ->and((string) $fulfilment->tableId())->toBe('table-7')
            ->and($fulfilment->reason())->toBeNull()
            ->and($fulfilment->unavailableLines())->toBe([]);
    });

    test('is confirmed once the kitchen opened a ticket', function () {
        $fulfilment = fulfilmentAwaitingTheKitchen();

        $fulfilment->confirm();

        expect($fulfilment->status())->toBe(FulfilmentStatus::CONFIRMED);
    });

    test('can be restored in any state', function () {
        $fulfilment = OrderFulfilment::restore(
            new TableOrderId('order-1'),
            new TableId('table-7'),
            FulfilmentStatus::CANCELLED,
            'The guests gave up.',
            [steakUnavailable()],
            ORDER_PLACED_AT,
        );

        expect($fulfilment->status())->toBe(FulfilmentStatus::CANCELLED)
            ->and($fulfilment->reason())->toBe('The guests gave up.')
            ->and($fulfilment->unavailableLines())->toEqual([steakUnavailable()])
            ->and($fulfilment->requestPosition())->toBe(ORDER_PLACED_AT);
    });

    describe('when the kitchen cannot cook some lines', function () {
        test('awaits a revision, with the lines that are unavailable', function () {
            $fulfilment = fulfilmentAwaitingRevision();

            expect($fulfilment->status())->toBe(FulfilmentStatus::AWAITING_REVISION)
                ->and($fulfilment->unavailableLines())->toEqual([steakUnavailable()]);
        });

        test('awaits a revision even when the kitchen opened no ticket at all', function () {
            $fulfilment = fulfilmentAwaitingTheKitchen();

            $fulfilment->awaitRevision([steakUnavailable()], ORDER_PLACED_AT);

            expect($fulfilment->status())->toBe(FulfilmentStatus::AWAITING_REVISION);
        });

        test('hearing it twice changes nothing', function () {
            $fulfilment = fulfilmentAwaitingRevision();

            $fulfilment->awaitRevision([steakUnavailable()], ORDER_PLACED_AT);

            expect($fulfilment->status())->toBe(FulfilmentStatus::AWAITING_REVISION)
                ->and($fulfilment->unavailableLines())->toHaveCount(1);
        });

        test('an answer to an order older than the last revision is ignored', function () {
            $fulfilment = fulfilmentAwaitingRevision();
            $fulfilment->revise(20);

            $fulfilment->awaitRevision([steakUnavailable()], ORDER_PLACED_AT);

            expect($fulfilment->status())->toBe(FulfilmentStatus::AWAITING_KITCHEN)
                ->and($fulfilment->unavailableLines())->toBe([]);
        });

        test('a cancelled order stays cancelled', function () {
            $fulfilment = fulfilmentAwaitingRevision();
            $fulfilment->cancel('The guests gave up.');

            $fulfilment->awaitRevision([steakUnavailable()], ORDER_PLACED_AT);
            $fulfilment->confirm();

            expect($fulfilment->status())->toBe(FulfilmentStatus::CANCELLED);
        });
    });

    describe('when the dining room revises the order', function () {
        test('awaits the kitchen again, and the lines that were unavailable are abandoned', function () {
            $fulfilment = fulfilmentAwaitingRevision();

            $fulfilment->revise(20);

            expect($fulfilment->status())->toBe(FulfilmentStatus::AWAITING_KITCHEN)
                ->and($fulfilment->unavailableLines())->toBe([])
                ->and($fulfilment->requestPosition())->toBe(20);
        });

        test('can be revised again after the kitchen answered the revision', function () {
            $fulfilment = fulfilmentAwaitingRevision();
            $fulfilment->revise(20);
            $fulfilment->awaitRevision([steakUnavailable()], 20);

            $fulfilment->revise(30);

            expect($fulfilment->status())->toBe(FulfilmentStatus::AWAITING_KITCHEN)
                ->and($fulfilment->requestPosition())->toBe(30);
        });

        test('is not revised when nothing is unavailable', function () {
            $fulfilment = fulfilmentAwaitingTheKitchen();
            $fulfilment->confirm();

            expect(fn () => $fulfilment->revise(20))
                ->toThrow(FrontOfHouseException::class, 'Only an order awaiting a revision can be revised.');
        });

        test('is refused once the guests paid and left', function () {
            $fulfilment = fulfilmentAwaitingRevision();
            $fulfilment->close(30);

            expect(fn () => $fulfilment->revise(40))
                ->toThrow(ObsoleteRevision::class, 'The revision of the order is obsolete: the guests already paid and left.');
        });
    });

    describe('when the guests give up', function () {
        test('is cancelled, with the reason', function () {
            $fulfilment = fulfilmentAwaitingRevision();

            $fulfilment->cancel('The guests gave up.');

            expect($fulfilment->status())->toBe(FulfilmentStatus::CANCELLED)
                ->and($fulfilment->reason())->toBe('The guests gave up.')
                ->and($fulfilment->unavailableLines())->toEqual([steakUnavailable()]);
        });

        test('needs a reason', function () {
            expect(fn () => fulfilmentAwaitingRevision()->cancel('  '))
                ->toThrow(FrontOfHouseException::class, 'A cancellation needs a reason.');
        });

        test('cannot cancel what the kitchen is cooking', function () {
            $fulfilment = fulfilmentAwaitingTheKitchen();
            $fulfilment->confirm();

            expect(fn () => $fulfilment->cancel('Too late.'))
                ->toThrow(FrontOfHouseException::class, 'Only an order awaiting a revision can be cancelled.');
        });
    });

    describe('when the bill is paid', function () {
        test('an order still awaiting a revision is closed: the guests left', function () {
            $fulfilment = fulfilmentAwaitingRevision();

            $fulfilment->close(30);

            expect($fulfilment->status())->toBe(FulfilmentStatus::CLOSED);
        });

        test('a payment older than the order says nothing about it', function () {
            $fulfilment = fulfilmentAwaitingRevision();

            $fulfilment->close(ORDER_PLACED_AT - 1);

            expect($fulfilment->status())->toBe(FulfilmentStatus::AWAITING_REVISION);
        });

        test('an order the kitchen is cooking is left alone', function () {
            $fulfilment = fulfilmentAwaitingTheKitchen();
            $fulfilment->confirm();

            $fulfilment->close(30);

            expect($fulfilment->status())->toBe(FulfilmentStatus::CONFIRMED);
        });
    });
});
