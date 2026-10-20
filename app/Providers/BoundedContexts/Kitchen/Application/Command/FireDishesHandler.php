<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\CourseFired;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<FireDishes, CourseFired>
 */
#[AsCommandHandler(FireDishes::class)]
final readonly class FireDishesHandler implements CommandHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    public function __invoke(FireDishes $command): CourseFired
    {
        $ticket = $this->tickets->get($command->ticketId);
        $command->expectedVersion?->ensureMatches($ticket->expectedVersion());
        $ticket->fireCourse($command->course);
        $this->tickets->save($ticket);

        return new CourseFired($command->ticketId, $ticket->expectedVersion());
    }
}
