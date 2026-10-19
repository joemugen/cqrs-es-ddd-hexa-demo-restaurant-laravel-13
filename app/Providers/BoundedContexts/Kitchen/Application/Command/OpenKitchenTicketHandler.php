<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\TicketOpened;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<OpenKitchenTicket, TicketOpened>
 */
#[AsCommandHandler(OpenKitchenTicket::class)]
final readonly class OpenKitchenTicketHandler implements CommandHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    public function __invoke(OpenKitchenTicket $command): TicketOpened
    {
        if ($this->tickets->exists($command->ticketId)) {
            return new TicketOpened($command->ticketId);
        }

        $this->tickets->save(KitchenTicket::open(
            $command->ticketId,
            $command->partyRef,
            $command->tableLabel,
            $command->lines,
        ));

        return new TicketOpened($command->ticketId);
    }
}
