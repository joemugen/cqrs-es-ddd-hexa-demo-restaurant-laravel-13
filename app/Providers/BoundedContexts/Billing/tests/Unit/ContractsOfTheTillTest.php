<?php

use App\Providers\BoundedContexts\Billing\Application\Integration\BillOpenedContract;
use App\Providers\BoundedContexts\Billing\Application\Integration\BillPaidContract;
use App\Providers\BoundedContexts\Billing\Application\Integration\DishPlacedOnBillContract;
use App\Providers\BoundedContexts\Billing\Application\Integration\DishWrittenOffContract;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\DishKind;
use App\Providers\BoundedContexts\Billing\Domain\Event\BillOpened;
use App\Providers\BoundedContexts\Billing\Domain\Event\BillPaid;
use App\Providers\BoundedContexts\Billing\Domain\Event\DishPlacedOnBill;
use App\Providers\BoundedContexts\Billing\Domain\Event\DishWrittenOff;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;

function billingPublishedAs(string $contract): array
{
    $published = (new ReflectionClass($contract))->getAttributes(PublishedAs::class)[0]->newInstance();

    return [$published->type, $published->version];
}

describe('what the till tells the others', function () {
    test('a bill paid: whose cover it was, and which bill', function () {
        $contract = BillPaidContract::from(new BillPaid(new BillId('bill-1'), new CoverRef('visit-1')));

        expect(billingPublishedAs(BillPaidContract::class))->toBe(['billing.bill_paid', 1])
            ->and($contract->toPayload())->toBe(['coverRef' => 'visit-1', 'billId' => 'bill-1'])
            ->and(BillPaidContract::fromPayload($contract->toPayload()))->toEqual($contract);
    });

    test('a bill opened: which cover, which bill, and the dishes the ledger placed on it', function () {
        $contract = BillOpenedContract::from(new BillOpened(new BillId('bill-1'), new CoverRef('visit-1'), [new ServedDish(new SourceKey('order-1:line-1'), 'Green salad', 2, 1100, DishKind::FOOD)]));

        expect(billingPublishedAs(BillOpenedContract::class))->toBe(['billing.bill_opened', 1])
            ->and($contract->toPayload())->toBe([
                'coverRef' => 'visit-1',
                'billId' => 'bill-1',
                'dishes' => [['sourceKey' => 'order-1:line-1', 'description' => 'Green salad', 'quantity' => 2, 'unitPriceCents' => 1100, 'kind' => 'food']],
            ])
            ->and(BillOpenedContract::fromPayload($contract->toPayload()))->toEqual($contract);
    });

    test('a dish placed on a bill: which bill, and the dish', function () {
        $contract = DishPlacedOnBillContract::from(new DishPlacedOnBill(new BillId('bill-1'), new CoverRef('visit-1'), new ServedDish(new SourceKey('order-2:line-1'), 'Creme brulee', 1, 1100, DishKind::FOOD)));

        expect(billingPublishedAs(DishPlacedOnBillContract::class))->toBe(['billing.dish_placed_on_bill', 1])
            ->and($contract->toPayload())->toBe([
                'coverRef' => 'visit-1',
                'billId' => 'bill-1',
                'sourceKey' => 'order-2:line-1',
                'description' => 'Creme brulee',
                'quantity' => 1,
                'unitPriceCents' => 1100,
                'kind' => 'food',
            ])
            ->and(DishPlacedOnBillContract::fromPayload($contract->toPayload()))->toEqual($contract);
    });

    test('a dish written off: whose cover, which bill, which dish and how much was lost', function () {
        $contract = DishWrittenOffContract::from(new DishWrittenOff(new BillId('bill-1'), new CoverRef('visit-1'), new SourceKey('order-1:line-2'), 'Creme brulee', 2, 2200));

        expect(billingPublishedAs(DishWrittenOffContract::class))->toBe(['billing.dish_written_off', 1])
            ->and($contract->toPayload())->toBe([
                'coverRef' => 'visit-1',
                'billId' => 'bill-1',
                'sourceKey' => 'order-1:line-2',
                'description' => 'Creme brulee',
                'quantity' => 2,
                'amountCents' => 2200,
            ])
            ->and(DishWrittenOffContract::fromPayload($contract->toPayload()))->toEqual($contract);
    });
});
