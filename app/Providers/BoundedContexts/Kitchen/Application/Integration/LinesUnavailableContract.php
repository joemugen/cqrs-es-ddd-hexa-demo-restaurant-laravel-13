<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Integration;

use App\Providers\BoundedContexts\Kitchen\Domain\Event\LinesUnavailable;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenEvent;
use App\Providers\BoundedContexts\Kitchen\Domain\UnavailableLine;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * The kitchen cannot cook these lines, and why. orderPosition names the order, or the revision, it answers.
 */
#[PublishedAs(KitchenEvent::LINES_UNAVAILABLE, version: 1)]
#[Translates(LinesUnavailable::class)]
final readonly class LinesUnavailableContract implements IntegrationContract
{
    /**
     * @param  list<array{lineId: string, name: string, reason: string}>  $lines
     */
    public function __construct(
        public string $tableOrderId,
        public string $tableId,
        public int $orderPosition,
        public array $lines,
    ) {}

    public static function from(LinesUnavailable $event): self
    {
        return new self(
            (string) $event->ticketId,
            (string) $event->partyRef,
            $event->orderPosition,
            array_map(fn (UnavailableLine $line): array => $line->toPayload(), $event->lines),
        );
    }

    public function toPayload(): array
    {
        return [
            'tableOrderId' => $this->tableOrderId,
            'tableId' => $this->tableId,
            'orderPosition' => $this->orderPosition,
            'lines' => $this->lines,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) $payload['tableOrderId'], (string) $payload['tableId'], (int) $payload['orderPosition'], $payload['lines']);
    }
}
