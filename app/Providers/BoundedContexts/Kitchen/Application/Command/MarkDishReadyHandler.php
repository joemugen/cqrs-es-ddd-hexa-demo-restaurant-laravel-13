<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\DishMarkedReady;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<MarkDishReady, DishMarkedReady>
 */
#[AsCommandHandler(MarkDishReady::class)]
final readonly class MarkDishReadyHandler implements CommandHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    public function __invoke(MarkDishReady $command): DishMarkedReady
    {
        $ticket = $this->tickets->get($command->ticketId);
        $command->expectedVersion?->ensureMatches($ticket->expectedVersion());
        $ticket->markReady($command->lineId);
        $this->tickets->save($ticket);

        return new DishMarkedReady($command->ticketId, $ticket->expectedVersion());
    }
}
