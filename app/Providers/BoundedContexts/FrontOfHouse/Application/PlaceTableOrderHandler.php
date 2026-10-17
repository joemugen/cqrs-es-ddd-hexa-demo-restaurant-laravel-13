<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;

final readonly class PlaceTableOrderHandler
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
    public function handle(PlaceTableOrder $command): array
    {
        $this->unitOfWork->execute(function () use ($command): void {
            $table = $this->tables->get($command->tableId);
            $menu = $this->menus->containing(array_column($command->lines, 'menuItemId'));
            $order = TableOrder::place($command->tableOrderId, $table, $menu, $command->lines);

            $this->orders->save($order);
            $this->bus->publish('front_of_house.table_order_placed', [
                'tableOrderId' => $order->id(),
                'tableId' => $table->id(),
                'tableLabel' => $table->label(),
                'lines' => $order->forTheKitchen(),
            ]);
        });

        return [
            'tableOrderId' => $command->tableOrderId,
            'accepted' => true,
        ];
    }
}
