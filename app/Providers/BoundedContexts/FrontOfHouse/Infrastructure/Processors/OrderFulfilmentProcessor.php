<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Processors;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\BeginOrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\CloseOrdersOfPaidTable;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ConfirmOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\MarkOrderAwaitingRevision;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseEvent;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\UnavailableLine;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Application\Processor;
use App\Providers\Modules\SharedKernel\Infrastructure\Causation;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;

/**
 * The saga of an order, as the dining room sees it. It starts when the order is placed. The kitchen cooks what it can
 * (confirmed) and names what it cannot (awaiting a revision, until the waiter revises or the guests give up). A paid
 * bill closes an order still awaiting a revision.
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
                $event->position,
            ),
            $event->type->is(ConsumedEvents::KITCHEN_TICKET_OPENED),
            $event->type->is(ConsumedEvents::KITCHEN_DISHES_ADDED) => new ConfirmOrder(
                new TableOrderId((string) $event->payload['tableOrderId']),
            ),
            $event->type->is(ConsumedEvents::KITCHEN_LINES_UNAVAILABLE) => new MarkOrderAwaitingRevision(
                new TableOrderId((string) $event->payload['tableOrderId']),
                array_map(UnavailableLine::fromPayload(...), $event->payload['lines']),
                (int) $event->payload['orderPosition'],
            ),
            $event->type->is(ConsumedEvents::BILLING_BILL_PAID) => new CloseOrdersOfPaidTable(
                new TableId((string) $event->payload['coverRef']),
                $event->position,
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
