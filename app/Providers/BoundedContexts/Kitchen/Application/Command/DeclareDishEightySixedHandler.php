<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\DishEightySixed;
use App\Providers\BoundedContexts\Kitchen\Application\DishRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenEvent;
use App\Providers\Modules\EventStore\Application\IntegrationBus;
use App\Providers\Modules\EventStore\Domain\IntegrationEvent;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;

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
        $position = $this->bus->publish(IntegrationEvent::named(KitchenEvent::DISH_EIGHTY_SIXED, [
            'dishName' => (string) $dish->name(),
        ]));
        $this->dishes->recordEightySixedAt($dish->id(), $position);

        return new DishEightySixed($command->dishId);
    }
}
