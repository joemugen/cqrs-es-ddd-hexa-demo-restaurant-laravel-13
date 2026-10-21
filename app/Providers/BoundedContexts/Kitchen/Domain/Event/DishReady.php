<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain\Event;

use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\Modules\SharedKernel\Domain\StreamedDomainEvent;

/**
 * A dish of the ticket is ready at the pass.
 */
final readonly class DishReady implements StreamedDomainEvent
{
    public const STORED_AS = 'ticket.dish_ready';

    public function __construct(
        public KitchenTicketId $ticketId,
        public LineId $lineId,
        public DishName $dishName,
        public PartyRef $partyRef,
        public TableLabel $tableLabel,
        public int $quantity,
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
            'dishName' => (string) $this->dishName,
            'partyRef' => (string) $this->partyRef,
            'tableLabel' => (string) $this->tableLabel,
            'quantity' => $this->quantity,
        ];
    }

    public static function fromStored(array $stored): static
    {
        return new self(
            new KitchenTicketId((string) $stored['ticketId']),
            new LineId((string) $stored['lineId']),
            new DishName((string) $stored['dishName']),
            new PartyRef((string) $stored['partyRef']),
            new TableLabel((string) $stored['tableLabel']),
            (int) $stored['quantity'],
        );
    }
}
