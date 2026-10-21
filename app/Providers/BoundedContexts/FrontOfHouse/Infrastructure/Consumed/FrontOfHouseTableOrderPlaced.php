<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * An order was placed, and the room follows it: the saga starts. The room reads its own contract like the others do.
 */
#[Consumes(ConsumedEvents::FRONT_OF_HOUSE_TABLE_ORDER_PLACED, version: 1)]
final readonly class FrontOfHouseTableOrderPlaced implements ConsumedMessage
{
    public function __construct(
        public TableOrderId $tableOrderId,
        public TableId $tableId,
    ) {}

    public static function fromPayload(array $payload): static
    {
        return new self(new TableOrderId((string) $payload['tableOrderId']), new TableId((string) $payload['tableId']));
    }
}
