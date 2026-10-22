<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain\Event;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\Modules\SharedKernel\Domain\Internal;
use App\Providers\Modules\SharedKernel\Domain\StreamedDomainEvent;

/**
 * The guests are gone: what the ticket still asked to cook is not cooked. The room tells it itself, nobody outside reads it.
 */
#[Internal]
final readonly class TicketCancelled implements StreamedDomainEvent
{
    public const STORED_AS = 'ticket.cancelled';

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
