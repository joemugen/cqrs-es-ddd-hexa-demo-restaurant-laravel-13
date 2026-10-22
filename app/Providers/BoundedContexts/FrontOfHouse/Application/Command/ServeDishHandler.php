<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\DishServed;
use App\Providers\BoundedContexts\FrontOfHouse\Application\PassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<ServeDish, DishServed>
 */
#[AsCommandHandler(ServeDish::class)]
final readonly class ServeDishHandler implements CommandHandler
{
    public function __construct(
        private PassRepository $passes,
        private TableOrderRepository $orders,
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(ServeDish $command): DishServed
    {
        $dish = $this->passes->find($command->toServeId);
        $dish->serve($this->orders->get(new TableOrderId((string) $dish->ticketId()))->line($dish->lineId()));
        $this->passes->save($dish);
        $this->contracts->publish($dish->releaseEvents());

        return new DishServed($command->toServeId);
    }
}
