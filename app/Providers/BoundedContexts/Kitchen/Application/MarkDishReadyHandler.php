<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

final readonly class MarkDishReadyHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    /**
     * @return array{kitchenTicketId: string, accepted: true}
     */
    public function handle(MarkDishReady $command): array
    {
        $ticket = $this->tickets->get($command->ticketId);
        $ticket->markReady($command->lineId);
        $this->tickets->save($ticket);

        return [
            'kitchenTicketId' => $command->ticketId,
            'accepted' => true,
        ];
    }
}
