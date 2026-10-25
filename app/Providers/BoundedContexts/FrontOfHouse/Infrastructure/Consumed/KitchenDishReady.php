<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * A dish is ready, for a table: the room puts it at the pass, and its list to serve shows it.
 */
#[Consumes(ConsumedEvents::KITCHEN_DISH_READY, version: 1)]
final readonly class KitchenDishReady implements ConsumedMessage
{
    public function __construct(
        public TableOrderId $tableOrderId,
        public LineId $lineId,
        public DishName $dishName,
        public TableId $tableId,
        public TableLabel $tableLabel,
        public int $quantity,
    ) {}

    public static function fromPayload(array $payload): static
    {
        return new self(
            new TableOrderId((string) $payload['tableOrderId']),
            new LineId((string) $payload['lineId']),
            new DishName((string) $payload['dishName']),
            new TableId((string) $payload['tableId']),
            new TableLabel((string) $payload['tableLabel']),
            (int) $payload['quantity'],
        );
    }
}
