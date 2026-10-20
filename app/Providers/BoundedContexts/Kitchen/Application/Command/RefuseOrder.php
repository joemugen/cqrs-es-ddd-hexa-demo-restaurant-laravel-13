<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The kitchen cannot cook what the dining room ordered. It says so as a fact, and the dining room compensates.
 */
final readonly class RefuseOrder implements Command
{
    public function __construct(
        public KitchenTicketId $ticketId,
        public PartyRef $partyRef,
        public string $reason,
    ) {}
}
