<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * A visit is over: the orders of it that are not over are closed. Nothing can be ordered for it any more, so the list of them
 * is complete.
 */
#[Consumes(ConsumedEvents::FRONT_OF_HOUSE_TABLE_FREED, version: 1)]
final readonly class FrontOfHouseTableFreed implements ConsumedMessage
{
    public function __construct(public VisitId $visitId) {}

    public static function fromPayload(array $payload): static
    {
        return new self(new VisitId((string) $payload['visitId']));
    }
}
