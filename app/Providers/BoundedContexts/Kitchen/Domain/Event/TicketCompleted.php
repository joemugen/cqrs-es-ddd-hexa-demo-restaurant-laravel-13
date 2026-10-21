<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain\Event;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\Modules\SharedKernel\Domain\Internal;
use App\Providers\Modules\SharedKernel\Domain\StreamedDomainEvent;

/**
 * Every dish of the ticket is ready. Nobody outside the kitchen reads it: the pass reads the dishes.
 */
#[Internal]
final readonly class TicketCompleted implements StreamedDomainEvent
{
    public const STORED_AS = 'ticket.completed';

    public function __construct(public KitchenTicketId $ticketId) {}

    public function storedAs(): string
    {
        return self::STORED_AS;
    }

    public function toStored(): array
    {
        return ['ticketId' => (string) $this->ticketId];
    }

    public static function fromStored(array $stored): static
    {
        return new self(new KitchenTicketId((string) $stored['ticketId']));
    }
}
