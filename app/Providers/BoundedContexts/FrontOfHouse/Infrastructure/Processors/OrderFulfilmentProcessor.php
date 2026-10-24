<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Processors;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ApplyOrderRevision;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\BeginOrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\CloseOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ConfirmOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\MarkOrderAwaitingRevision;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\BillingBillPaid;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\FrontOfHouseTableOrderPlaced;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\FrontOfHouseTableOrderRevised;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenDishesAdded;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenLinesUnavailable;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenTicketOpened;
use App\Providers\Modules\EventStore\Application\ConsumedMessages;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Application\Processor;
use App\Providers\Modules\SharedKernel\Infrastructure\Causation;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;

/**
 * The saga of an order, as the dining room sees it. It starts when the order is placed. The kitchen cooks what it can
 * (confirmed) and names what it cannot (awaiting a revision, until the waiter revises or the guests give up). The
 * payment of a visit closes the orders of that visit that are not over: one command, and one transaction, for each of
 * them, since a command decides on one aggregate. Closing an order twice says nothing, so the payment may be heard again.
 */
final class OrderFulfilmentProcessor implements Processor
{
    public const CHECKPOINT = 'foh.order-fulfilment';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private FrontOfHouseCommandBus $bus,
        private OrderFulfilmentRepository $fulfilments,
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
        $message = $this->messages->read($event, self::class);

        $commands = match (true) {
            $message instanceof FrontOfHouseTableOrderPlaced => [new BeginOrderFulfilment($message->tableOrderId, $message->tableId, $message->visitId, $event->position)],
            $message instanceof KitchenTicketOpened,
            $message instanceof KitchenDishesAdded => [new ConfirmOrder($message->tableOrderId)],
            $message instanceof FrontOfHouseTableOrderRevised => [new ApplyOrderRevision($message->tableOrderId, $message->revision, $event->position)],
            $message instanceof KitchenLinesUnavailable => [new MarkOrderAwaitingRevision($message->tableOrderId, $message->lines, $message->orderPosition)],
            $message instanceof BillingBillPaid => $this->closingEachOpenOrderOf($message->visitId),
            default => [],
        };

        if ($commands === []) {
            return false;
        }

        Correlation::resume($event->correlationId);
        Causation::resume($event->position);

        foreach ($commands as $command) {
            $this->bus->dispatch($command);
        }

        return true;
    }

    /**
     * Read in the write model, not in a projection that may be late, so that no order of the visit escapes the list.
     *
     * @return list<CloseOrder>
     */
    private function closingEachOpenOrderOf(VisitId $visit): array
    {
        return array_map(
            fn (OrderFulfilment $fulfilment): CloseOrder => new CloseOrder($fulfilment->id()),
            $this->fulfilments->openOf($visit),
        );
    }
}
