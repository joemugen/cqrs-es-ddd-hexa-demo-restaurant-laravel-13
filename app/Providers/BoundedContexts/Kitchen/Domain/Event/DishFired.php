<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain\Event;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\Modules\SharedKernel\Domain\Internal;
use App\Providers\Modules\SharedKernel\Domain\StreamedDomainEvent;

/**
 * A dish of the ticket was fired: the station starts cooking it. It matters to the kitchen alone.
 */
#[Internal]
final readonly class DishFired implements StreamedDomainEvent
{
    public const STORED_AS = 'ticket.dish_fired';

    public function __construct(
        public KitchenTicketId $ticketId,
        public LineId $lineId,
        public string $course,
    ) {}

    public function storedAs(): string
    {
        return self::STORED_AS;
    }

    public function toStored(): array
    {
        return [
            'ticketId' => (string) $this->ticketId,
            'lineId' => (string) $this->lineId,
            'course' => $this->course,
        ];
    }

    public static function fromStored(array $stored): static
    {
        return new self(
            new KitchenTicketId((string) $stored['ticketId']),
            new LineId((string) $stored['lineId']),
            (string) $stored['course'],
        );
    }
}
