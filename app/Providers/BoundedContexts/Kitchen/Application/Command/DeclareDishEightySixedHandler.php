<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\EventStore\Domain\IntegrationEvent;
use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\DishEightySixed;
use App\Providers\BoundedContexts\Kitchen\Application\DishRepository;
use App\Providers\BoundedContexts\Kitchen\Application\UnitOfWork;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<DeclareDishEightySixed, DishEightySixed>
 */
final readonly class DeclareDishEightySixedHandler implements CommandHandler
{
    public function __construct(
        private DishRepository $dishes,
        private IntegrationBus $bus,
        private UnitOfWork $unitOfWork,
    ) {}

    public function handle(Command $command): DishEightySixed
    {
        assert($command instanceof DeclareDishEightySixed);

        $this->unitOfWork->execute(function () use ($command): void {
            $dish = $this->dishes->get($command->dishId);
            $dish->eightySix();
            $this->dishes->save($dish);
            $this->bus->publish(IntegrationEvent::named('kitchen.dish_eighty_sixed', [
                'dishName' => (string) $dish->name(),
            ]));
        });

        return new DishEightySixed($command->dishId);
    }
}
