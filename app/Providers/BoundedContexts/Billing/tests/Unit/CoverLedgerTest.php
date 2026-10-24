<?php

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverLedger;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\DishKind;
use App\Providers\BoundedContexts\Billing\Domain\Event\BillOpened;
use App\Providers\BoundedContexts\Billing\Domain\Event\DishPlacedOnBill;
use App\Providers\BoundedContexts\Billing\Domain\Event\DishWrittenOff;
use App\Providers\BoundedContexts\Billing\Domain\Placement;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;

function aDishOfTheLedger(string $key, string $description = 'Green salad'): ServedDish
{
    return new ServedDish(new SourceKey($key), $description, 1, 1100, DishKind::FOOD);
}

function anEmptyLedger(): CoverLedger
{
    return CoverLedger::open(new CoverRef('visit-1'));
}

describe('the ledger of a cover', function () {
    describe('when a dish is served and no bill was asked for yet', function () {
        test('keeps it unplaced: it waits for a bill', function () {
            $ledger = anEmptyLedger();

            $ledger->record(aDishOfTheLedger('order-1:line-1'), 10);

            expect($ledger->placementOf(new SourceKey('order-1:line-1')))->toBe(Placement::Unplaced)
                ->and($ledger->releaseEvents())->toBe([]);
        });

        test('keeps it once when it is told twice', function () {
            $ledger = anEmptyLedger();
            $ledger->record(aDishOfTheLedger('order-1:line-1'), 10);
            $ledger->openBill(new BillId('bill-1'));
            $ledger->releaseEvents();

            $ledger->record(aDishOfTheLedger('order-1:line-1'), 10);

            expect($ledger->releaseEvents())->toBe([]);
        });
    });

    describe('when a bill is asked for', function () {
        test('places every dish served so far on that bill, and says which', function () {
            $ledger = anEmptyLedger();
            $ledger->record(aDishOfTheLedger('order-1:line-1'), 10);
            $ledger->record(aDishOfTheLedger('order-1:line-2', 'Steak frites'), 11);

            $opened = $ledger->openBill(new BillId('bill-1'));

            expect($opened)->toEqual(new BillId('bill-1'))
                ->and($ledger->placementOf(new SourceKey('order-1:line-1')))->toBe(Placement::OnBill)
                ->and($ledger->placementOf(new SourceKey('order-1:line-2')))->toBe(Placement::OnBill)
                ->and($ledger->releaseEvents())->toEqual([new BillOpened(
                    new BillId('bill-1'),
                    new CoverRef('visit-1'),
                    [aDishOfTheLedger('order-1:line-1'), aDishOfTheLedger('order-1:line-2', 'Steak frites')],
                )]);
        });

        test('opens nothing when nothing was served', function () {
            $ledger = anEmptyLedger();

            expect($ledger->openBill(new BillId('bill-1')))->toBeNull()
                ->and($ledger->releaseEvents())->toBe([]);
        });

        test('answers with the bill that is already open, and says nothing again', function () {
            $ledger = anEmptyLedger();
            $ledger->record(aDishOfTheLedger('order-1:line-1'), 10);
            $ledger->openBill(new BillId('bill-1'));
            $ledger->releaseEvents();

            $again = $ledger->openBill(new BillId('bill-2'));

            expect($again)->toEqual(new BillId('bill-1'))
                ->and($ledger->releaseEvents())->toBe([]);
        });
    });

    describe('when a dish is served while a bill is open', function () {
        test('places it on that bill, and says so: the bill must receive it', function () {
            $ledger = anEmptyLedger();
            $ledger->record(aDishOfTheLedger('order-1:line-1'), 10);
            $ledger->openBill(new BillId('bill-1'));
            $ledger->releaseEvents();

            $ledger->record(aDishOfTheLedger('order-2:line-1', 'Creme brulee'), 12);

            expect($ledger->placementOf(new SourceKey('order-2:line-1')))->toBe(Placement::OnBill)
                ->and($ledger->releaseEvents())->toEqual([new DishPlacedOnBill(new BillId('bill-1'), new CoverRef('visit-1'), aDishOfTheLedger('order-2:line-1', 'Creme brulee'))]);
        });
    });

    describe('when a dish is served after the bill was paid', function () {
        test('writes it off, and says the loss', function () {
            $ledger = anEmptyLedger();
            $ledger->record(aDishOfTheLedger('order-1:line-1'), 10);
            $ledger->openBill(new BillId('bill-1'));
            $ledger->settle(new BillId('bill-1'));
            $ledger->releaseEvents();

            $ledger->record(aDishOfTheLedger('order-2:line-1', 'Creme brulee'), 12);

            expect($ledger->placementOf(new SourceKey('order-2:line-1')))->toBe(Placement::WrittenOff)
                ->and($ledger->releaseEvents())->toEqual([new DishWrittenOff(new BillId('bill-1'), new CoverRef('visit-1'), new SourceKey('order-2:line-1'), 'Creme brulee', 1, 1100)]);
        });

        test('ignores the payment of a bill that is not the current one', function () {
            $ledger = anEmptyLedger();
            $ledger->record(aDishOfTheLedger('order-1:line-1'), 10);
            $ledger->openBill(new BillId('bill-2'));
            $ledger->settle(new BillId('bill-1'));
            $ledger->releaseEvents();

            $ledger->record(aDishOfTheLedger('order-2:line-1'), 12);

            expect($ledger->placementOf(new SourceKey('order-2:line-1')))->toBe(Placement::OnBill);
        });
    });
});
