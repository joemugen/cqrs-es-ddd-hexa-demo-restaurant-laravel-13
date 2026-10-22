<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * The bill of a cover is paid. The cover of the till is a visit of the room.
 */
#[Consumes(ConsumedEvents::BILLING_BILL_PAID, version: 1)]
final readonly class BillingBillPaid implements ConsumedMessage
{
    public function __construct(public VisitId $visitId) {}

    public static function fromPayload(array $payload): static
    {
        return new self(new VisitId((string) $payload['coverRef']));
    }
}
