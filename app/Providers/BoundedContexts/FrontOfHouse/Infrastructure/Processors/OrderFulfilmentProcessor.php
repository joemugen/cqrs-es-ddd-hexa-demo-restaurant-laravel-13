<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Processors;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\BeginOrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\CancelOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ConfirmOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Application\Processor;
use App\Providers\Modules\SharedKernel\Infrastructure\Causation;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;

/**
 * The saga of an order, as the dining room sees it. It starts when the order is placed, and ends when the kitchen
 * opens a ticket (confirmed) or refuses the order (cancelled: the compensation).
 */
final class OrderFulfilmentProcessor implements Processor
{
    public const CHECKPOINT = 'foh.order-fulfilment';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private FrontOfHouseCommandBus $bus,
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
            if ($this->react($event)) {
                $handled++;
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }

    private function react(StoredEvent $event): bool
    {
        $command = match (true) {
            $event->type->is(FrontOfHouseEvent::TABLE_ORDER_PLACED) => new BeginOrderFulfilment(
                new TableOrderId((string) $event->payload['tableOrderId']),
                new TableId((string) $event->payload['tableId']),
            ),
            $event->type->is(ConsumedEvents::KITCHEN_TICKET_OPENED) => new ConfirmOrder(
                new TableOrderId((string) $event->payload['ticketId']),
            ),
            $event->type->is(ConsumedEvents::KITCHEN_ORDER_REFUSED) => new CancelOrder(
                new TableOrderId((string) $event->payload['tableOrderId']),
                (string) $event->payload['reason'],
            ),
            default => null,
        };

        if ($command === null) {
            return false;
        }

        Correlation::resume($event->correlationId);

        Causation::resume($event->position);
        $this->bus->dispatch($command);

        return true;
    }
}
