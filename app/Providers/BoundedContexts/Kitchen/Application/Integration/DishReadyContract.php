<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Integration;

use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishReady;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenEvent;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * A dish of the order is ready at the pass, for this table: the dining room serves it.
 */
#[PublishedAs(KitchenEvent::DISH_READY, version: 1)]
#[Translates(DishReady::class)]
final readonly class DishReadyContract implements IntegrationContract
{
    public function __construct(
        public string $tableOrderId,
        public string $lineId,
        public string $dishName,
        public string $tableId,
        public string $tableLabel,
        public int $quantity,
    ) {}

    public static function from(DishReady $event): self
    {
        return new self(
            (string) $event->ticketId,
            (string) $event->lineId,
            (string) $event->dishName,
            (string) $event->partyRef,
            (string) $event->tableLabel,
            $event->quantity,
        );
    }

    public function toPayload(): array
    {
        return [
            'tableOrderId' => $this->tableOrderId,
            'lineId' => $this->lineId,
            'dishName' => $this->dishName,
            'tableId' => $this->tableId,
            'tableLabel' => $this->tableLabel,
            'quantity' => $this->quantity,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            (string) $payload['tableOrderId'],
            (string) $payload['lineId'],
            (string) $payload['dishName'],
            (string) $payload['tableId'],
            (string) $payload['tableLabel'],
            (int) $payload['quantity'],
        );
    }
}
