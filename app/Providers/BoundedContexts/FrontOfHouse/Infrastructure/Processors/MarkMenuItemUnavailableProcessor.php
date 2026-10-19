<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Processors;

use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Processor;

/**
 * Reacts once to a shortage. Replaying it is not how the menu learns a dish is 86.
 */
final class MarkMenuItemUnavailableProcessor implements Processor
{
    public const CHECKPOINT = 'foh.menu-availability';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private MenuRepository $menus,
    ) {}

    public function consume(): int
    {
        $handled = 0;
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($event->type->is(ConsumedEvents::KITCHEN_DISH_EIGHTY_SIXED)) {
                $handled++;
                $item = $this->menus->findByName(new DishName((string) $event->payload['dishName']));
                $item->markUnavailable();
                $this->menus->save($item);
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }
}
