<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\TableOrderPlaced;
use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<PlaceTableOrder, TableOrderPlaced>
 */
#[AsCommandHandler(PlaceTableOrder::class)]
final readonly class PlaceTableOrderHandler implements CommandHandler
{
    public function __construct(
        private TableRepository $tables,
        private MenuRepository $menus,
        private TableOrderRepository $orders,
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(PlaceTableOrder $command): TableOrderPlaced
    {
        if ($this->orders->exists($command->tableOrderId)) {
            return new TableOrderPlaced($command->tableOrderId);
        }

        $table = $this->tables->get($command->tableId);
        $menu = $this->menus->containing(array_map(
            fn (RequestedLine $line): MenuItemId => $line->menuItemId,
            $command->lines,
        ));
        $order = TableOrder::place($command->tableOrderId, $table, $menu, $command->lines);

        $this->orders->save($order);
        $this->contracts->publish($order->releaseEvents());

        return new TableOrderPlaced($command->tableOrderId);
    }
}
