<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Consumed;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\BoundedContexts\Billing\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * The ledger placed dishes on a new bill: the bill is opened with them.
 */
#[Consumes(ConsumedEvents::BILLING_BILL_OPENED, version: 1)]
final readonly class BillingBillOpened implements ConsumedMessage
{
    /**
     * @param  list<ServedDish>  $dishes
     */
    public function __construct(
        public BillId $billId,
        public CoverRef $coverRef,
        public array $dishes,
    ) {}

    public static function fromPayload(array $payload): static
    {
        return new self(
            new BillId((string) $payload['billId']),
            new CoverRef((string) $payload['coverRef']),
            array_map(fn (array $dish): ServedDish => new ServedDish(
                new SourceKey((string) $dish['sourceKey']),
                (string) $dish['description'],
                (int) $dish['quantity'],
                (int) $dish['unitPriceCents'],
                (string) $dish['kind'],
            ), array_values($payload['dishes'])),
        );
    }
}
