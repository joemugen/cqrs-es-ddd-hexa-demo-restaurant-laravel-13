<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Consumed;

use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * The guests of a table asked for their bill: the till prepares it for their cover.
 */
#[Consumes(ConsumedEvents::FRONT_OF_HOUSE_BILL_REQUESTED, version: 1)]
final readonly class FrontOfHouseBillRequested implements ConsumedMessage
{
    public function __construct(public CoverRef $coverRef) {}

    public static function fromPayload(array $payload): static
    {
        return new self(new CoverRef((string) $payload['visitId']));
    }
}
