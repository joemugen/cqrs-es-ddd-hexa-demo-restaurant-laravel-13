<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\ServeDishAccepted;
use App\Providers\BoundedContexts\FrontOfHouse\Application\PassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<ServeDish, ServeDishAccepted>
 */
#[AsCommandHandler(ServeDish::class)]
final readonly class ServeDishHandler implements CommandHandler
{
    public function __construct(
        private PassRepository $passes,
        private TableOrderRepository $orders,
        private TableRepository $tables,
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(ServeDish $command): ServeDishAccepted
    {
        $dish = $this->passes->find($command->dishAtThePassId);
        $order = $this->orders->get($dish->tableOrderId());
        $dish->serve($order->line($dish->lineId()), $this->tables->get($order->tableId()), $order->visitId());
        $this->passes->save($dish);
        $this->contracts->publish($dish->releaseEvents());

        return new ServeDishAccepted($command->dishAtThePassId);
    }
}
