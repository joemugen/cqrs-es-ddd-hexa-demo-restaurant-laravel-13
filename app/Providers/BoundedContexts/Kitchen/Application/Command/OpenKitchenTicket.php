<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;

final readonly class OpenKitchenTicket implements Command
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
