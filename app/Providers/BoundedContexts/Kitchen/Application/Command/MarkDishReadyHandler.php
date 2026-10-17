<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\DishMarkedReady;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<MarkDishReady, DishMarkedReady>
 */
final readonly class MarkDishReadyHandler implements CommandHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    public function handle(Command $command): DishMarkedReady
    {
        assert($command instanceof MarkDishReady);

        $ticket = $this->tickets->get($command->ticketId);
        $ticket->markReady($command->lineId);
        $this->tickets->save($ticket);

        return new DishMarkedReady($command->ticketId);
    }
}
