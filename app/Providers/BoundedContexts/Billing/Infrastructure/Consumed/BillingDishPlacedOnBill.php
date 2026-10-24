<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Consumed;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\BoundedContexts\Billing\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * The ledger placed a dish on an open bill: the bill receives it.
 */
#[Consumes(ConsumedEvents::BILLING_DISH_PLACED_ON_BILL, version: 1)]
final readonly class BillingDishPlacedOnBill implements ConsumedMessage
{
    public function __construct(
        public BillId $billId,
        public ServedDish $dish,
    ) {}

    public static function fromPayload(array $payload): static
    {
        return new self(
            new BillId((string) $payload['billId']),
            new ServedDish(
                new SourceKey((string) $payload['sourceKey']),
                (string) $payload['description'],
                (int) $payload['quantity'],
                (int) $payload['unitPriceCents'],
                (string) $payload['kind'],
            ),
        );
    }
}
