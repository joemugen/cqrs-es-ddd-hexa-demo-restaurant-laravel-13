<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\OrderRevised;
use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<ReviseOrder, OrderRevised>
 */
#[AsCommandHandler(ReviseOrder::class)]
final readonly class ReviseOrderHandler implements CommandHandler
{
    public function __construct(
        private OrderFulfilmentRepository $fulfilments,
        private TableOrderRepository $orders,
        private TableRepository $tables,
        private MenuRepository $menus,
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(ReviseOrder $command): OrderRevised
    {
        $fulfilment = $this->fulfilments->get($command->tableOrderId);
        $fulfilment->ensureCanBeRevised();
        $order = $this->orders->get($command->tableOrderId);
        $order->revise(
            $this->tables->get($fulfilment->tableId()),
            $this->menus->containing(array_map(fn (RequestedLine $line): MenuItemId => $line->menuItemId, $command->lines)),
            $command->lines,
        );
        $this->orders->save($order);

        $fulfilment->revise(array_last($this->contracts->publish($order->releaseEvents())));
        $this->fulfilments->save($fulfilment);

        return new OrderRevised($command->tableOrderId);
    }
}
