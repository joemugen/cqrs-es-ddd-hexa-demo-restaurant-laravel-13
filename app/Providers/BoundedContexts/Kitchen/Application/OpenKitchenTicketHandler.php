<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;

final readonly class OpenKitchenTicketHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    /**
     * @return array{kitchenTicketId: string, accepted: true}
     */
    public function handle(OpenKitchenTicket $command): array
    {
        if ($this->tickets->exists($command->ticketId)) {
            return [
                'kitchenTicketId' => $command->ticketId,
                'accepted' => true,
            ];
        }

        $this->tickets->save(KitchenTicket::open(
            $command->ticketId,
            $command->partyRef,
            $command->tableLabel,
            $command->lines,
        ));

        return [
            'kitchenTicketId' => $command->ticketId,
            'accepted' => true,
        ];
    }
}
