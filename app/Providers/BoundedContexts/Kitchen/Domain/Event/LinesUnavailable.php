<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain\Event;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\UnavailableLine;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * The kitchen cannot cook some lines of an order, or of its revision.
 */
final readonly class LinesUnavailable implements TypedDomainEvent
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
