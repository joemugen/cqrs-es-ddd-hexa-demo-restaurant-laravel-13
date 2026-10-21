<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Integration;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderCancelled;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * Why an order was cancelled, and which lines are abandoned.
 */
#[PublishedAs(FrontOfHouseEvent::TABLE_ORDER_CANCELLED, version: 1)]
#[Translates(TableOrderCancelled::class)]
final readonly class TableOrderCancelledV1 implements IntegrationContract
{
    /**
     * @param  list<string>  $abandonedLines
     */
    public function __construct(
        public string $tableOrderId,
        public string $tableId,
        public string $reason,
        public array $abandonedLines,
    ) {}

    public static function from(TableOrderCancelled $event): self
    {
        return new self(
            (string) $event->tableOrderId,
            (string) $event->tableId,
            $event->reason,
            array_map(fn (LineId $line): string => (string) $line, $event->abandonedLines),
        );
    }

    public function toPayload(): array
    {
        return [
            'tableOrderId' => $this->tableOrderId,
            'tableId' => $this->tableId,
            'reason' => $this->reason,
            'abandonedLines' => $this->abandonedLines,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) $payload['tableOrderId'], (string) $payload['tableId'], (string) $payload['reason'], $payload['abandonedLines']);
    }
}
