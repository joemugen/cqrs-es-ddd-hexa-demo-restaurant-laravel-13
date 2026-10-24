<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\ReviseOrderAccepted;
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
 * @implements CommandHandler<ReviseOrder, ReviseOrderAccepted>
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

    /**
     * The command writes the order and nothing else: the saga that follows it learns of the revision from its fact.
     */
    public function __invoke(ReviseOrder $command): ReviseOrderAccepted
    {
        $order = $this->orders->get($command->tableOrderId);
        $this->fulfilments->get($command->tableOrderId)->ensureCanBeRevised($order->revisions());

        $order->revise(
            $this->tables->get($order->tableId()),
            $this->menus->containing(array_map(fn (RequestedLine $line): MenuItemId => $line->menuItemId, $command->lines)),
            $command->lines,
        );
        $this->orders->save($order);
        $this->contracts->publish($order->releaseEvents());

        return new ReviseOrderAccepted($command->tableOrderId);
    }
}
