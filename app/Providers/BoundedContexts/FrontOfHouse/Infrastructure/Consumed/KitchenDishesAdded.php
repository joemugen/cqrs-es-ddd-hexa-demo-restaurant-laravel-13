<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * Dishes joined the ticket of an order: all the room needs is which order.
 */
#[Consumes(ConsumedEvents::KITCHEN_DISHES_ADDED, version: 1)]
final readonly class KitchenDishesAdded implements ConsumedMessage
{
    public function __construct(public TableOrderId $tableOrderId) {}

    public static function fromPayload(array $payload): static
    {
        return new self(new TableOrderId((string) $payload['tableOrderId']));
    }
}
