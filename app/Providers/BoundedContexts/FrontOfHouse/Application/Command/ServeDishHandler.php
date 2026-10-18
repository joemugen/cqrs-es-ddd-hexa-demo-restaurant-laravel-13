<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\EventStore\Domain\IntegrationEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response\DishServed;
use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\PassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;

/**
 * @implements CommandHandler<ServeDish, DishServed>
 */
#[AsCommandHandler(ServeDish::class)]
final readonly class ServeDishHandler implements CommandHandler
{
    public function __construct(
        private PassRepository $passes,
        private MenuRepository $menus,
        private IntegrationBus $bus,
    ) {}

    public function __invoke(ServeDish $command): DishServed
    {
        $dish = $this->passes->find($command->toServeId);
        $dish->serve();
        $item = $this->menus->findByName($dish->dishName());
        $this->passes->save($dish);
        $this->bus->publish(IntegrationEvent::named(FrontOfHouseEvent::DISH_SERVED, [
            'tableId' => (string) $dish->tableId(),
            'description' => (string) $item->name(),
            'quantity' => $dish->quantity(),
            'unitPriceCents' => $item->priceCents(),
            'kind' => $item->kind(),
            'sourceKey' => $dish->ticketId().':'.$dish->lineId(),
        ]));

        return new DishServed($command->toServeId);
    }
}
