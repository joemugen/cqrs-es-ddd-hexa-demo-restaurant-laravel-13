<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\DishEightySixed;
use App\Providers\BoundedContexts\Kitchen\Application\DishRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenEvent;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;
use App\Providers\Modules\EventStore\Application\IntegrationBus;
use App\Providers\Modules\EventStore\Domain\IntegrationEvent;

/**
 * @implements CommandHandler<DeclareDishEightySixed, DishEightySixed>
 */
#[AsCommandHandler(DeclareDishEightySixed::class)]
final readonly class DeclareDishEightySixedHandler implements CommandHandler
{
    public function __construct(
        private DishRepository $dishes,
        private IntegrationBus $bus,
    ) {}

    public function __invoke(DeclareDishEightySixed $command): DishEightySixed
    {
        $dish = $this->dishes->get($command->dishId);
        $dish->eightySix();
        $this->dishes->save($dish);
        $this->bus->publish(IntegrationEvent::named(KitchenEvent::DISH_EIGHTY_SIXED, [
            'dishName' => (string) $dish->name(),
        ]));

        return new DishEightySixed($command->dishId);
    }
}
