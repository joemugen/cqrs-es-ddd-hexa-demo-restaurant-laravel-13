<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<FireDishes>
 */
final readonly class FireDishesHandler implements CommandHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    /**
     * @return array{kitchenTicketId: string, accepted: true}
     */
    public function handle(Command $command): array
    {
        assert($command instanceof FireDishes);

        $ticket = $this->tickets->get($command->ticketId);
        $ticket->fireCourse($command->course);
        $this->tickets->save($ticket);

        return [
            'kitchenTicketId' => $command->ticketId,
            'accepted' => true,
        ];
    }
}
