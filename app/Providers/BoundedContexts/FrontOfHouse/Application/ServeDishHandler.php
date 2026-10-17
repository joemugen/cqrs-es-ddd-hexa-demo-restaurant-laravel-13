<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;

final readonly class ServeDishHandler
{
    public function __construct(
        private PassRepository $passes,
        private MenuRepository $menus,
        private IntegrationBus $bus,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @return array{toServeId: string, accepted: true}
     */
    public function handle(ServeDish $command): array
    {
        $this->unitOfWork->execute(function () use ($command): void {
            $dish = $this->passes->find($command->toServeId);
            $dish->serve();
            $item = $this->menus->findByName($dish->dishName());
            $this->passes->save($dish);
            $this->bus->publish('front_of_house.dish_served', [
                'tableId' => $dish->tableId(),
                'description' => $item->name(),
                'quantity' => $dish->quantity(),
                'unitPriceCents' => $item->priceCents(),
                'kind' => $item->kind(),
                'sourceKey' => $dish->ticketId().':'.$dish->lineId(),
            ]);
        });

        return [
            'toServeId' => $command->toServeId,
            'accepted' => true,
        ];
    }
}
