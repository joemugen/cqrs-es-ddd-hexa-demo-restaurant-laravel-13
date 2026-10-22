<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\TicketCancellationNoted;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<CancelTicket, TicketCancellationNoted>
 */
#[AsCommandHandler(CancelTicket::class)]
final readonly class CancelTicketHandler implements CommandHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    /**
     * An order that never got a ticket has nothing to cancel.
     */
    public function __invoke(CancelTicket $command): TicketCancellationNoted
    {
        if ($this->tickets->exists($command->ticketId)) {
            $ticket = $this->tickets->get($command->ticketId);
            $ticket->cancel();
            $this->tickets->save($ticket);
        }

        return new TicketCancellationNoted($command->ticketId);
    }
}
