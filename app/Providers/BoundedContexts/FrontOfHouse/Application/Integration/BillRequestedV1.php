<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Integration;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\BillRequested;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * The guests of this table asked for their bill.
 */
#[PublishedAs(FrontOfHouseEvent::BILL_REQUESTED, version: 1)]
#[Translates(BillRequested::class)]
final readonly class BillRequestedV1 implements IntegrationContract
{
    public function __construct(public string $tableId) {}

    public static function from(BillRequested $event): self
    {
        return new self((string) $event->tableId);
    }

    public function toPayload(): array
    {
        return ['tableId' => $this->tableId];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) $payload['tableId']);
    }
}
