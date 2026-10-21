<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Integration;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderRevised;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderLine;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * What the kitchen needs of a revision: the lines it adds to the order.
 */
#[PublishedAs(FrontOfHouseEvent::TABLE_ORDER_REVISED, version: 1)]
#[Translates(TableOrderRevised::class)]
final readonly class TableOrderRevisedV1 implements IntegrationContract
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

    public static function from(TableOrderRevised $event): self
    {
        return new self(
            (string) $event->tableOrderId,
            (string) $event->tableId,
            (string) $event->tableLabel,
            array_map(fn (OrderLine $line): array => $line->forTheKitchen(), $event->addedLines),
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
