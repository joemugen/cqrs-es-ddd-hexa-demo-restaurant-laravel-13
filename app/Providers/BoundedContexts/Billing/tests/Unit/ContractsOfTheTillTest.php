<?php

use App\Providers\BoundedContexts\Billing\Application\Integration\BillPaidV1;
use App\Providers\BoundedContexts\Billing\Application\Integration\DishWrittenOffV1;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\Event\BillPaid;
use App\Providers\BoundedContexts\Billing\Domain\Event\DishWrittenOff;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;

function billingPublishedAs(string $contract): array
{
    $published = (new ReflectionClass($contract))->getAttributes(PublishedAs::class)[0]->newInstance();

    return [$published->type, $published->version];
}

describe('what the till tells the others', function () {
    test('a bill paid: whose cover it was, and which bill', function () {
        $contract = BillPaidV1::from(new BillPaid(new BillId('bill-1'), new CoverRef('visit-1')));

        expect(billingPublishedAs(BillPaidV1::class))->toBe(['billing.bill_paid', 1])
            ->and($contract->toPayload())->toBe(['coverRef' => 'visit-1', 'billId' => 'bill-1'])
            ->and(BillPaidV1::fromPayload($contract->toPayload()))->toEqual($contract);
    });

    test('a dish written off: whose cover, which bill, which dish and how much was lost', function () {
        $contract = DishWrittenOffV1::from(new DishWrittenOff(new BillId('bill-1'), new CoverRef('visit-1'), new SourceKey('order-1:line-2'), 'Creme brulee', 2, 2200));

        expect(billingPublishedAs(DishWrittenOffV1::class))->toBe(['billing.dish_written_off', 1])
            ->and($contract->toPayload())->toBe([
                'coverRef' => 'visit-1',
                'billId' => 'bill-1',
                'sourceKey' => 'order-1:line-2',
                'description' => 'Creme brulee',
                'quantity' => 2,
                'amountCents' => 2200,
            ])
            ->and(DishWrittenOffV1::fromPayload($contract->toPayload()))->toEqual($contract);
    });
});
