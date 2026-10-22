<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The guests of the order paid and left: the kitchen stops what it still cooks for them.
 */
final readonly class CancelTicket implements Command
{
    public function __construct(public KitchenTicketId $ticketId) {}
}
