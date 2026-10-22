<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Integration;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderClosed;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * The guests paid and left: the kitchen stops what it still cooks for this order.
 */
#[PublishedAs(FrontOfHouseEvent::TABLE_ORDER_CLOSED, version: 1)]
#[Translates(TableOrderClosed::class)]
final readonly class TableOrderClosedV1 implements IntegrationContract
{
    public function __construct(
        public string $tableOrderId,
        public string $tableId,
    ) {}

    public static function from(TableOrderClosed $event): self
    {
        return new self((string) $event->tableOrderId, (string) $event->tableId);
    }

    public function toPayload(): array
    {
        return [
            'tableOrderId' => $this->tableOrderId,
            'tableId' => $this->tableId,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) $payload['tableOrderId'], (string) $payload['tableId']);
    }
}
