<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

final readonly class FireDishesHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    /**
     * @return array{kitchenTicketId: string, accepted: true}
     */
    public function handle(FireDishes $command): array
    {
        $ticket = $this->tickets->get($command->ticketId);
        $ticket->fireCourse($command->course);
        $this->tickets->save($ticket);

        return [
            'kitchenTicketId' => $command->ticketId,
            'accepted' => true,
        ];
    }
}
