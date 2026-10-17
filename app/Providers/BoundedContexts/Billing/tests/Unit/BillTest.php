<?php

use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\BoundedContexts\Billing\Domain\BillingException;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\Vat;

function servedDish(string $description, int $unitPriceCents, int $quantity, string $kind): ServedDish
{
    return new ServedDish($description, $description, $quantity, $unitPriceCents, $kind);
}

test('vat is 10 percent of food eaten in and 20 percent of alcohol', function () {
    expect(Vat::cents(1100, 'food'))->toBe(100)
        ->and(Vat::cents(2200, 'food'))->toBe(200)
        ->and(Vat::cents(600, 'alcohol'))->toBe(100)
        ->and(Vat::cents(1200, 'alcohol'))->toBe(200);
});

test('a bill charges only the served dishes and splits a payment with a tip', function () {
    $bill = Bill::prepare('bill-1', 'table-4', [
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
    expect(fn () => Bill::prepare('bill-1', 'table-4', []))
        ->toThrow(BillingException::class, 'Nothing has been served.');
});

test('a payment cannot exceed the balance and a tip cannot be negative', function () {
    $bill = Bill::prepare('bill-1', 'table-4', [
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
    $bill = Bill::prepare('bill-1', 'table-4', [
        servedDish('Green salad', 1100, 1, 'food'),
    ]);
    $bill->pay(1100, 200);

    expect(fn () => $bill->pay(1, 0))
        ->toThrow(BillingException::class, 'A paid bill cannot be changed.');
});
