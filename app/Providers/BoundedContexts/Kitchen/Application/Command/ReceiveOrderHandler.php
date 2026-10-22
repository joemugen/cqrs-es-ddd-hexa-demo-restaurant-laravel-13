<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\OrderReceived;
use App\Providers\BoundedContexts\Kitchen\Application\DishRepository;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderAssessment;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<ReceiveOrder, OrderReceived>
 */
#[AsCommandHandler(ReceiveOrder::class)]
final readonly class ReceiveOrderHandler implements CommandHandler
{
    public function __construct(
        private DishRepository $dishes,
        private KitchenTicketRepository $tickets,
        private ContractPublisher $contracts,
    ) {}

    /**
     * The ticket first, so that what the kitchen cooks is in the log before what it cannot.
     */
    public function __invoke(ReceiveOrder $command): OrderReceived
    {
        $assessed = OrderAssessment::of(
            $command->ticketId,
            $command->partyRef,
            $command->lines,
            $this->dishes->called(array_map(fn (OrderedLine $line) => $line->name, $command->lines)),
            $command->orderPosition,
        );

        if ($assessed->cookable !== []) {
            $this->tickets->save($this->ticketWith($command, $assessed->cookable));
        }

        if ($assessed->unavailable !== null) {
            $this->contracts->publish([$assessed->unavailable]);
        }

        return new OrderReceived($command->ticketId);
    }

    /**
     * @param  list<TicketLine>  $cookable
     */
    private function ticketWith(ReceiveOrder $command, array $cookable): KitchenTicket
    {
        if (! $this->tickets->exists($command->ticketId)) {
            return KitchenTicket::open($command->ticketId, $command->partyRef, $command->tableLabel, $cookable);
        }

        $ticket = $this->tickets->get($command->ticketId);
        $ticket->addDishes($cookable);

        return $ticket;
    }
}
