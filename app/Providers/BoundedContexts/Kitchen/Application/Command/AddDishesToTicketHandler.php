<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\DishesAdded;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<AddDishesToTicket, DishesAdded>
 */
#[AsCommandHandler(AddDishesToTicket::class)]
final readonly class AddDishesToTicketHandler implements CommandHandler
{
    public function __construct(private KitchenTicketRepository $tickets) {}

    public function __invoke(AddDishesToTicket $command): DishesAdded
    {
        $this->tickets->save(
            $this->tickets->exists($command->ticketId)
                ? $this->ticketWithDishes($command)
                : KitchenTicket::open($command->ticketId, $command->partyRef, $command->tableLabel, $command->lines),
        );

        return new DishesAdded($command->ticketId);
    }

    private function ticketWithDishes(AddDishesToTicket $command): KitchenTicket
    {
        $ticket = $this->tickets->get($command->ticketId);
        $ticket->addDishes($command->lines);

        return $ticket;
    }
}
