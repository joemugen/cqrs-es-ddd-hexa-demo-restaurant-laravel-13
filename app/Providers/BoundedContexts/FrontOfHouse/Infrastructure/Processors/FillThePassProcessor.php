<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Processors;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\PutDishAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenDishReady;
use App\Providers\Modules\EventStore\Application\ConsumedMessages;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Processor;
use App\Providers\Modules\SharedKernel\Infrastructure\Causation;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;

/**
 * The dishes the kitchen has ready are put at the pass, which is where the room decides what was served. This is not the
 * list to serve: that one is a reading of the pass, and it may lag.
 */
final class FillThePassProcessor implements Processor
{
    public const CHECKPOINT = 'foh.pass';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private FrontOfHouseCommandBus $bus,
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

            if ($message instanceof KitchenDishReady) {
                $handled++;
                Correlation::resume($event->correlationId);
                Causation::resume($event->position);
                $this->bus->dispatch(new PutDishAtThePass($message->ticketId, $message->lineId, $message->tableId, $message->dishName, $message->quantity));
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }
}
