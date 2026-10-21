<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\UnavailableLine;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The kitchen cannot cook some lines of an order. It says so as a fact, and the dining room asks the guests again.
 */
final readonly class DeclareLinesUnavailable implements Command
{
    /**
     * @param  list<UnavailableLine>  $lines
     * @param  int  $orderPosition  where the order, or its revision, sits in the log: the fact answers that one
     */
    public function __construct(
        public KitchenTicketId $ticketId,
        public PartyRef $partyRef,
        public int $orderPosition,
        public array $lines,
    ) {}
}
