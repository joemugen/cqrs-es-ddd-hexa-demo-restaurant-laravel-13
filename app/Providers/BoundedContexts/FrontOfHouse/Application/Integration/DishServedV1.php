<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Integration;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\DishServed;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * What the till bills of a dish that was served, and where it comes from: the same source is billed once.
 */
#[PublishedAs(FrontOfHouseEvent::DISH_SERVED, version: 1)]
#[Translates(DishServed::class)]
final readonly class DishServedV1 implements IntegrationContract
{
    public function __construct(
        public string $tableId,
        public string $visitId,
        public string $tableOrderId,
        public string $lineId,
        public string $description,
        public int $quantity,
        public int $unitPriceCents,
        public string $kind,
        public string $sourceKey,
    ) {}

    public static function from(DishServed $event): self
    {
        return new self(
            (string) $event->tableId,
            (string) $event->visitId,
            (string) $event->tableOrderId,
            (string) $event->lineId,
            (string) $event->dishName,
            $event->quantity,
            $event->unitPriceCents,
            $event->kind,
            $event->tableOrderId.':'.$event->lineId,
        );
    }

    public function toPayload(): array
    {
        return [
            'tableId' => $this->tableId,
            'visitId' => $this->visitId,
            'tableOrderId' => $this->tableOrderId,
            'lineId' => $this->lineId,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitPriceCents' => $this->unitPriceCents,
            'kind' => $this->kind,
            'sourceKey' => $this->sourceKey,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            (string) $payload['tableId'],
            (string) $payload['visitId'],
            (string) $payload['tableOrderId'],
            (string) $payload['lineId'],
            (string) $payload['description'],
            (int) $payload['quantity'],
            (int) $payload['unitPriceCents'],
            (string) $payload['kind'],
            (string) $payload['sourceKey'],
        );
    }
}
