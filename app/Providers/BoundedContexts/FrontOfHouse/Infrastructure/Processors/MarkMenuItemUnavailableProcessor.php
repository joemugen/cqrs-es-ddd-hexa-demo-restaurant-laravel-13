<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Processors;

use App\Providers\BoundedContexts\EventStore\Domain\CheckpointStore;
use App\Providers\BoundedContexts\EventStore\Domain\EventStore;
use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;

/**
 * Reacts once to a shortage. Replaying it is not how the menu learns a dish is 86.
 */
final class MarkMenuItemUnavailableProcessor
{
    public const CHECKPOINT = 'foh.menu-availability';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private MenuRepository $menus,
    ) {}

    public function run(): void
    {
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($event->type === 'kitchen.dish_eighty_sixed') {
                $item = $this->menus->findByName((string) $event->payload['dishName']);
                $item->markUnavailable();
                $this->menus->save($item);
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);
    }
}
