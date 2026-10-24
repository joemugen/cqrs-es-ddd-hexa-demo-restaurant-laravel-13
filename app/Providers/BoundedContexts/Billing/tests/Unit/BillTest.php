<?php

use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\BillingException;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\Event\BillPaid;
use App\Providers\BoundedContexts\Billing\Domain\Event\DishWrittenOff;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\BoundedContexts\Billing\Domain\Vat;

function servedDish(string $description, int $unitPriceCents, int $quantity, string $kind): ServedDish
{
    return new ServedDish(new SourceKey('ticket-1:'.strtolower(str_replace(' ', '-', $description))), $description, $quantity, $unitPriceCents, $kind);
}

describe('Bill', function () {
    test('vat is 10 percent of food eaten in and 20 percent of alcohol', function () {
        expect(Vat::cents(1100, 'food'))->toBe(100)
            ->and(Vat::cents(2200, 'food'))->toBe(200)
            ->and(Vat::cents(600, 'alcohol'))->toBe(100)
            ->and(Vat::cents(1200, 'alcohol'))->toBe(200);
    });

    test('a bill charges only the served dishes and splits a payment with a tip', function () {
        $bill = Bill::prepare(new BillId('bill-1'), new CoverRef('table-4'), [
            servedDish('Green salad', 1100, 1, 'food'),
            servedDish('Steak frites', 2200, 1, 'food'),
            servedDish('Creme brulee', 1100, 2, 'food'),
            servedDish('House red', 600, 2, 'alcohol'),
        ]);

        expect($bill->foodGrossCents())->toBe(5500)
            ->and($bill->alcoholGrossCents())->toBe(1200)
            ->and($bill->vatCents())->toBe(700)
            ->and($bill->totalCents())->toBe(6700)
            ->and($bill->balanceCents())->toBe(6700)
            ->and($bill->isPaid())->toBeFalse();

        $bill->pay(4000, 0);

        expect($bill->balanceCents())->toBe(2700)
            ->and($bill->tipCents())->toBe(0)
            ->and($bill->isPaid())->toBeFalse();

        $bill->pay(2700, 500);

        expect($bill->balanceCents())->toBe(0)
            ->and($bill->tipCents())->toBe(500)
            ->and($bill->isPaid())->toBeTrue();
    });

    test('nothing served means there is no bill', function () {
        expect(fn () => Bill::prepare(new BillId('bill-1'), new CoverRef('table-4'), []))
            ->toThrow(BillingException::class, 'Nothing has been served.');
    });

    test('a payment cannot exceed the balance and a tip cannot be negative', function () {
        $bill = Bill::prepare(new BillId('bill-1'), new CoverRef('table-4'), [
            servedDish('Green salad', 1100, 1, 'food'),
        ]);

        expect(fn () => $bill->pay(1101, 0))
            ->toThrow(BillingException::class, 'That payment is more than the balance.');
        expect(fn () => $bill->pay(1100, -1))
            ->toThrow(BillingException::class, 'A tip cannot be negative.');
        expect(fn () => $bill->pay(0, 500))
            ->toThrow(BillingException::class, 'A payment needs an amount.');
    });

    test('a paid bill is immutable', function () {
        $bill = Bill::prepare(new BillId('bill-1'), new CoverRef('table-4'), [
            servedDish('Green salad', 1100, 1, 'food'),
        ]);
        $bill->pay(1100, 200);

        expect(fn () => $bill->pay(1, 0))
            ->toThrow(BillingException::class, 'A paid bill cannot be changed.');
    });

    describe('when a dish is served after it was prepared', function () {
        test('starts at version 1, and takes the dish as a line: the bill moves to its next version', function () {
            $bill = Bill::prepare(new BillId('bill-1'), new CoverRef('table-4'), [servedDish('Green salad', 1100, 1, 'food')]);
            expect($bill->version())->toBe(1);

            $bill->receive(servedDish('Creme brulee', 1100, 1, 'food'));

            expect($bill->version())->toBe(2)
                ->and($bill->totalCents())->toBe(2200)
                ->and($bill->balanceCents())->toBe(2200)
                ->and($bill->lines())->toHaveCount(2);
        });

        test('takes a dish told twice once, and stays at the same version', function () {
            $bill = Bill::prepare(new BillId('bill-1'), new CoverRef('table-4'), [servedDish('Green salad', 1100, 1, 'food')]);
            $bill->receive(servedDish('Creme brulee', 1100, 1, 'food'));

            $bill->receive(servedDish('Creme brulee', 1100, 1, 'food'));

            expect($bill->version())->toBe(2)
                ->and($bill->lines())->toHaveCount(2);
        });
    });

    describe('when a dish is served after it was paid', function () {
        test('does not change the bill, and says the loss as a fact: the dish is written off', function () {
            $bill = Bill::prepare(new BillId('bill-1'), new CoverRef('visit-1'), [servedDish('Green salad', 1100, 1, 'food')]);
            $bill->pay(1100, 0);
            $bill->releaseEvents();

            $bill->receive(servedDish('Creme brulee', 1100, 2, 'food'));

            expect($bill->lines())->toHaveCount(1)
                ->and($bill->version())->toBe(1)
                ->and($bill->totalCents())->toBe(1100)
                ->and($bill->releaseEvents())->toEqual([
                    new DishWrittenOff(new BillId('bill-1'), new CoverRef('visit-1'), new SourceKey('ticket-1:creme-brulee'), 'Creme brulee', 2, 2200),
                ]);
        });

        test('records nothing when the bill is still open: the dish goes on it', function () {
            $bill = Bill::prepare(new BillId('bill-1'), new CoverRef('visit-1'), [servedDish('Green salad', 1100, 1, 'food')]);

            $bill->receive(servedDish('Creme brulee', 1100, 1, 'food'));

            expect($bill->releaseEvents())->toBe([]);
        });
    });

    describe('when paid', function () {
        function aBillOf1100(): Bill
        {
            return Bill::prepare(new BillId('bill-1'), new CoverRef('table-4'), [servedDish('Green salad', 1100, 1, 'food')]);
        }

        test('records that it is paid once the balance reaches zero, and not before', function () {
            $bill = aBillOf1100();

            $bill->pay(600, 0);
            expect($bill->releaseEvents())->toBe([]);

            $bill->pay(500, 0);
            expect($bill->releaseEvents())->toEqual([new BillPaid(new BillId('bill-1'), new CoverRef('table-4'))]);
        });
    });
});
