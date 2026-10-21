<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\UnavailableLine;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * The kitchen cannot cook some lines of an order: which, why, and the order or revision it answers.
 */
#[Consumes(ConsumedEvents::KITCHEN_LINES_UNAVAILABLE, version: 1)]
final readonly class KitchenLinesUnavailable implements ConsumedMessage
{
    /**
     * @param  list<UnavailableLine>  $lines
     */
    public function __construct(
        public TableOrderId $tableOrderId,
        public int $orderPosition,
        public array $lines,
    ) {}

    public static function fromPayload(array $payload): static
    {
        return new self(
            new TableOrderId((string) $payload['tableOrderId']),
            (int) $payload['orderPosition'],
            array_map(UnavailableLine::fromPayload(...), $payload['lines']),
        );
    }
}
