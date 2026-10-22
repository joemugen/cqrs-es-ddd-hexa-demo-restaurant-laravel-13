<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The dining room sent lines to cook, for an order or for its revision. The kitchen reads them against what it has:
 * the ticket gets what it can cook, and the rest is told as a fact.
 */
final readonly class ReceiveOrder implements Command
{
    /**
     * @param  list<OrderedLine>  $lines
     * @param  int  $orderPosition  where the order, or its revision, sits in the log
     */
    public function __construct(
        public KitchenTicketId $ticketId,
        public PartyRef $partyRef,
        public TableLabel $tableLabel,
        public array $lines,
        public int $orderPosition,
    ) {}
}
