<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * The guests revised an order. The room follows it, as a saga does: it only needs which order and which revision.
 */
#[Consumes(ConsumedEvents::FRONT_OF_HOUSE_TABLE_ORDER_REVISED, version: 1)]
final readonly class FrontOfHouseTableOrderRevised implements ConsumedMessage
{
    public function __construct(
        public TableOrderId $tableOrderId,
        public int $revision,
    ) {}

    public static function fromPayload(array $payload): static
    {
        return new self(new TableOrderId((string) $payload['tableOrderId']), (int) $payload['revision']);
    }
}
