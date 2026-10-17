<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;

final readonly class DeclareDishEightySixedHandler
{
    public function __construct(
        private DishRepository $dishes,
        private IntegrationBus $bus,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @return array{dishId: string, accepted: true}
     */
    public function handle(DeclareDishEightySixed $command): array
    {
        $this->unitOfWork->execute(function () use ($command): void {
            $dish = $this->dishes->get($command->dishId);
            $dish->eightySix();
            $this->dishes->save($dish);
            $this->bus->publish('kitchen.dish_eighty_sixed', [
                'dishName' => $dish->name(),
            ]);
        });

        return [
            'dishId' => $command->dishId,
            'accepted' => true,
        ];
    }
}
