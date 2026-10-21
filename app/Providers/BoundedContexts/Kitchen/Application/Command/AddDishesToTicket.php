<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The dining room revised an order: its available dishes join the ticket, which opens if the kitchen had none yet.
 */
final readonly class AddDishesToTicket implements Command
{
    /**
     * @param  list<TicketLine>  $lines
     */
    public function __construct(
        public KitchenTicketId $ticketId,
        public PartyRef $partyRef,
        public TableLabel $tableLabel,
        public array $lines,
    ) {}
}
