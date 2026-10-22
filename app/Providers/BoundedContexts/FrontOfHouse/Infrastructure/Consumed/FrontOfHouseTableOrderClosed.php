<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * The guests of an order paid and left: what waited for them at the pass is taken off the list to serve.
 */
#[Consumes(ConsumedEvents::FRONT_OF_HOUSE_TABLE_ORDER_CLOSED, version: 1)]
final readonly class FrontOfHouseTableOrderClosed implements ConsumedMessage
{
    public function __construct(public TableOrderId $tableOrderId) {}

    public static function fromPayload(array $payload): static
    {
        return new self(new TableOrderId((string) $payload['tableOrderId']));
    }
}
