<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\EventStore\Domain\IntegrationEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\UnitOfWork;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<PlaceTableOrder>
 */
final readonly class PlaceTableOrderHandler implements CommandHandler
{
    public function __construct(
        private TableRepository $tables,
        private MenuRepository $menus,
        private TableOrderRepository $orders,
        private IntegrationBus $bus,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @return array{tableOrderId: string, accepted: true}
     */
    public function handle(Command $command): array
    {
        assert($command instanceof PlaceTableOrder);

        $this->unitOfWork->execute(function () use ($command): void {
            $table = $this->tables->get($command->tableId);
            $menu = $this->menus->containing(array_map(
                fn (RequestedLine $line): MenuItemId => $line->menuItemId,
                $command->lines,
            ));
            $order = TableOrder::place($command->tableOrderId, $table, $menu, $command->lines);

            $this->orders->save($order);
            $this->bus->publish(IntegrationEvent::named('front_of_house.table_order_placed', [
                'tableOrderId' => (string) $order->id(),
                'tableId' => (string) $table->id(),
                'tableLabel' => (string) $table->label(),
                'lines' => $order->forTheKitchen(),
            ]));
        });

        return [
            'tableOrderId' => (string) $command->tableOrderId,
            'accepted' => true,
        ];
    }
}
