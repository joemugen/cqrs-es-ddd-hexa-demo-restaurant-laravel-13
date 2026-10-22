<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Processors;

use App\Providers\BoundedContexts\Kitchen\Application\Command\ReceiveOrder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenCommandBus;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Consumed\OrderToCook;
use App\Providers\Modules\EventStore\Application\ConsumedMessages;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Processor;
use App\Providers\Modules\SharedKernel\Infrastructure\Causation;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;

/**
 * A processor reacts once. It is not a projection, but replaying it is harmless: the kitchen opens a ticket and adds a
 * line only when they are not there yet.
 */
final class OpenKitchenTicketProcessor implements Processor
{
    public const CHECKPOINT = 'kitchen.open-ticket';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private KitchenCommandBus $bus,
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
            $order = $this->messages->read($event, self::class);

            if ($order instanceof OrderToCook) {
                $handled++;
                Correlation::resume($event->correlationId);
                Causation::resume($event->position);
                $this->bus->dispatch(new ReceiveOrder($order->ticketId, $order->partyRef, $order->tableLabel, $order->lines, $event->position));
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }
}
