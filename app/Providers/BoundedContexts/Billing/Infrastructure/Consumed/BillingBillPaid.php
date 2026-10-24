<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Consumed;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * A bill was paid: the ledger knows that the dishes served from now on are on no bill.
 */
#[Consumes(ConsumedEvents::BILLING_BILL_PAID, version: 1)]
final readonly class BillingBillPaid implements ConsumedMessage
{
    public function __construct(
        public CoverRef $coverRef,
        public BillId $billId,
    ) {}

    public static function fromPayload(array $payload): static
    {
        return new self(new CoverRef((string) $payload['coverRef']), new BillId((string) $payload['billId']));
    }
}
