<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\CancelTicketAccepted;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<CancelTicket, CancelTicketAccepted>
 */
#[AsCommandHandler(CancelTicket::class)]
final readonly class CancelTicketHandler implements CommandHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    /**
     * An order that never got a ticket has nothing to cancel.
     */
    public function __invoke(CancelTicket $command): CancelTicketAccepted
    {
        if ($this->tickets->exists($command->ticketId)) {
            $ticket = $this->tickets->get($command->ticketId);
            $ticket->cancel();
            $this->tickets->save($ticket);
        }

        return new CancelTicketAccepted($command->ticketId);
    }
}
