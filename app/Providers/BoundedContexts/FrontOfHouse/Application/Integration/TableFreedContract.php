<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Integration;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableFreed;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * The guests of a visit are gone and the table is free: the visit is over.
 */
#[PublishedAs(FrontOfHouseEvent::TABLE_FREED, version: 1)]
#[Translates(TableFreed::class)]
final readonly class TableFreedContract implements IntegrationContract
{
    public function __construct(
        public string $tableId,
        public string $visitId,
    ) {}

    public static function from(TableFreed $event): self
    {
        return new self((string) $event->tableId, (string) $event->visitId);
    }

    public function toPayload(): array
    {
        return [
            'tableId' => $this->tableId,
            'visitId' => $this->visitId,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) $payload['tableId'], (string) $payload['visitId']);
    }
}
