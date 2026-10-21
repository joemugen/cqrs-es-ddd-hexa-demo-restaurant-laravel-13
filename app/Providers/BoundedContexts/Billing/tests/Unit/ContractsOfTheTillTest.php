<?php

use App\Providers\BoundedContexts\Billing\Application\Integration\BillPaidV1;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\Event\BillPaid;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;

describe('what the till tells the others', function () {
    test('a bill paid: whose cover it was, and which bill', function () {
        $contract = BillPaidV1::from(new BillPaid(new BillId('bill-1'), new CoverRef('table-4')));
        $published = (new ReflectionClass(BillPaidV1::class))->getAttributes(PublishedAs::class)[0]->newInstance();

        expect([$published->type, $published->version])->toBe(['billing.bill_paid', 1])
            ->and($contract->toPayload())->toBe(['coverRef' => 'table-4', 'billId' => 'bill-1'])
            ->and(BillPaidV1::fromPayload($contract->toPayload()))->toEqual($contract);
    });
});
