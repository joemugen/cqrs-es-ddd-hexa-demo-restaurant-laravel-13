<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain\Event;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * Dishes joined a ticket that was already open. This is the fact of the ticket: other contexts read its contract.
 */
final readonly class DishesAdded implements TypedDomainEvent
{
    public const STORED_AS = 'ticket.dishes_added';

    /**
     * @param  list<TicketLine>  $lines
     */
    public function __construct(
        public KitchenTicketId $ticketId,
        public array $lines,
    ) {}

    public function storedAs(): string
    {
        return self::STORED_AS;
    }

    public function toStored(): array
    {
        return [
            'ticketId' => (string) $this->ticketId,
            'lines' => array_map(fn (TicketLine $line): array => $line->toPayload(), $this->lines),
        ];
    }

    public static function fromStored(array $stored): static
    {
        return new self(
            new KitchenTicketId((string) $stored['ticketId']),
            array_map(TicketLine::fromPayload(...), $stored['lines']),
        );
    }
}
