<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * A dish left the pass for a table. The room reads its own contract, as the others do, to take the dish off its list.
 */
#[Consumes(ConsumedEvents::FRONT_OF_HOUSE_DISH_SERVED, version: 1)]
final readonly class FrontOfHouseDishServed implements ConsumedMessage
{
    public function __construct(public DishAtThePassId $dishAtThePassId) {}

    public static function fromPayload(array $payload): static
    {
        return new self(DishAtThePassId::forLine(new TableOrderId((string) $payload['tableOrderId']), new LineId((string) $payload['lineId'])));
    }
}
