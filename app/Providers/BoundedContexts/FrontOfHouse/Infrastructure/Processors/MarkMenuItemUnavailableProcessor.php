<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Processors;

use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenDishEightySixed;
use App\Providers\Modules\EventStore\Application\ConsumedMessages;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Processor;
use App\Providers\Modules\SharedKernel\Infrastructure\Causation;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;

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
        private ConsumedMessages $messages,
    ) {}

    public function hasWork(): bool
    {
        return $this->events->hasEventsAfter($this->checkpoints->get(self::CHECKPOINT));
    }

    public function consume(): int
    {
        $handled = 0;
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            $message = $this->messages->read($event, self::class);

            if ($message instanceof KitchenDishEightySixed) {
                $handled++;
                Correlation::resume($event->correlationId);
                Causation::resume($event->position);
                $item = $this->menus->findByName($message->dishName);
                $item->markUnavailable();
                $this->menus->save($item);
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }
}
