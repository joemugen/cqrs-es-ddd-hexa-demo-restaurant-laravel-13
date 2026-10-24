<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Processors;

use App\Providers\BoundedContexts\Billing\Application\Command\RecordServedDish;
use App\Providers\BoundedContexts\Billing\Infrastructure\Bus\BillingCommandBus;
use App\Providers\BoundedContexts\Billing\Infrastructure\Consumed\FrontOfHouseDishServed;
use App\Providers\Modules\EventStore\Application\ConsumedMessages;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Processor;
use App\Providers\Modules\SharedKernel\Infrastructure\Causation;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;

/**
 * Copies a served dish onto the till: on the bill that is open, or on the ledger that waits for one. It is not replayed: a
 * charge is taken once.
 */
final class RecordServedDishes implements Processor
{
    public const CHECKPOINT = 'billing.served';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private BillingCommandBus $bus,
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

            if ($message instanceof FrontOfHouseDishServed) {
                $handled++;
                Correlation::resume($event->correlationId);
                Causation::resume($event->position);
                $this->bus->dispatch(new RecordServedDish($message->coverRef, $message->dish, $event->position));
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }
}
