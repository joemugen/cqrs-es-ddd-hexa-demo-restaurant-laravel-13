<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain\Event;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\Modules\SharedKernel\Domain\StreamedDomainEvent;

/**
 * The kitchen opened the ticket of an order, with the dishes it can cook.
 */
final readonly class TicketOpened implements StreamedDomainEvent
{
    public const STORED_AS = 'ticket.opened';

    /**
     * @param  list<TicketLine>  $lines
     */
    public function __construct(
        public KitchenTicketId $ticketId,
        public PartyRef $partyRef,
        public TableLabel $tableLabel,
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
            'partyRef' => (string) $this->partyRef,
            'tableLabel' => (string) $this->tableLabel,
            'lines' => array_map(fn (TicketLine $line): array => $line->toPayload(), $this->lines),
        ];
    }

    public static function fromStored(array $stored): static
    {
        return new self(
            new KitchenTicketId((string) $stored['ticketId']),
            new PartyRef((string) $stored['partyRef']),
            new TableLabel((string) $stored['tableLabel']),
            array_map(TicketLine::fromPayload(...), $stored['lines']),
        );
    }
}
