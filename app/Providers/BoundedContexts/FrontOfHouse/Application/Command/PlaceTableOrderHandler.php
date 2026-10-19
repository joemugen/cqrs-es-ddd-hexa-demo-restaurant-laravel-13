<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\TableOrderPlaced;
use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;
use App\Providers\Modules\EventStore\Application\IntegrationBus;
use App\Providers\Modules\EventStore\Domain\IntegrationEvent;

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
        private IntegrationBus $bus,
    ) {}

    public function __invoke(PlaceTableOrder $command): TableOrderPlaced
    {
        $table = $this->tables->get($command->tableId);
        $menu = $this->menus->containing(array_map(
            fn (RequestedLine $line): MenuItemId => $line->menuItemId,
            $command->lines,
        ));
        $order = TableOrder::place($command->tableOrderId, $table, $menu, $command->lines);

        $this->orders->save($order);
        $this->bus->publish(IntegrationEvent::named(FrontOfHouseEvent::TABLE_ORDER_PLACED, [
            'tableOrderId' => (string) $order->id(),
            'tableId' => (string) $table->id(),
            'tableLabel' => (string) $table->label(),
            'lines' => $order->forTheKitchen(),
        ]));

        return new TableOrderPlaced($command->tableOrderId);
    }
}
