<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Integration;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderPlaced;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderLine;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * What the kitchen needs of an order that was placed: the table and the lines, never the price.
 */
#[PublishedAs(FrontOfHouseEvent::TABLE_ORDER_PLACED, version: 1)]
#[Translates(TableOrderPlaced::class)]
final readonly class TableOrderPlacedV1 implements IntegrationContract
{
    /**
     * @param  list<array{lineId: string, name: string, quantity: int, course: string, allergens: list<string>}>  $lines
     */
    public function __construct(
        public string $tableOrderId,
        public string $tableId,
        public string $tableLabel,
        public array $lines,
    ) {}

    public static function from(TableOrderPlaced $event): self
    {
        return new self(
            (string) $event->tableOrderId,
            (string) $event->tableId,
            (string) $event->tableLabel,
            array_map(fn (OrderLine $line): array => $line->forTheKitchen(), $event->lines),
        );
    }

    public function toPayload(): array
    {
        return [
            'tableOrderId' => $this->tableOrderId,
            'tableId' => $this->tableId,
            'tableLabel' => $this->tableLabel,
            'lines' => $this->lines,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) $payload['tableOrderId'], (string) $payload['tableId'], (string) $payload['tableLabel'], $payload['lines']);
    }
}
