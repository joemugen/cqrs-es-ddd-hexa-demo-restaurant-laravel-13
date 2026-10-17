<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<OpenKitchenTicket>
 */
final readonly class OpenKitchenTicketHandler implements CommandHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    /**
     * @return array{kitchenTicketId: string, accepted: true}
     */
    public function handle(Command $command): array
    {
        assert($command instanceof OpenKitchenTicket);

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
