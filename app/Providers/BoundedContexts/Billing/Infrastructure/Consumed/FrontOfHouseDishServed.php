<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Consumed;

use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\BoundedContexts\Billing\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * A dish left the pass for a table: the till has a line to bill, once.
 */
#[Consumes(ConsumedEvents::FRONT_OF_HOUSE_DISH_SERVED, version: 1)]
final readonly class FrontOfHouseDishServed implements ConsumedMessage
{
    public function __construct(
        public CoverRef $coverRef,
        public ServedDish $dish,
    ) {}

    public static function fromPayload(array $payload): static
    {
        return new self(
            new CoverRef((string) $payload['tableId']),
            new ServedDish(
                sourceKey: new SourceKey((string) $payload['sourceKey']),
                description: (string) $payload['description'],
                quantity: (int) $payload['quantity'],
                unitPriceCents: (int) $payload['unitPriceCents'],
                kind: (string) $payload['kind'],
            ),
        );
    }
}
