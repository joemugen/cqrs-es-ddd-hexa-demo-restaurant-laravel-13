<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\DishEightySixed;
use App\Providers\BoundedContexts\Kitchen\Application\DishRepository;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
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
        private ContractPublisher $contracts,
    ) {}

    public function __invoke(DeclareDishEightySixed $command): DishEightySixed
    {
        $dish = $this->dishes->get($command->dishId);
        $dish->eightySix();
        $this->dishes->save($dish);
        $this->dishes->recordEightySixedAt($dish->id(), array_last($this->contracts->publish($dish->releaseEvents())));

        return new DishEightySixed($command->dishId);
    }
}
