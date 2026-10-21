<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Processors;

use App\Providers\BoundedContexts\Kitchen\Application\Command\AddDishesToTicket;
use App\Providers\BoundedContexts\Kitchen\Application\Command\DeclareLinesUnavailable;
use App\Providers\BoundedContexts\Kitchen\Application\Command\OpenKitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Application\DishCatalog;
use App\Providers\BoundedContexts\Kitchen\Domain\LinesAvailability;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenCommandBus;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Consumed\FrontOfHouseTableOrderPlaced;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Consumed\OrderToCook;
use App\Providers\Modules\EventStore\Application\ConsumedMessages;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
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
        private DishCatalog $dishes,
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
                $this->cookWhatIsAvailable($event, $order);
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }

    /**
     * What the kitchen can cook goes to the ticket at once. What it cannot is not a failure to retry: it becomes a fact
     * the dining room answers, and the orders behind go on. The position of the event, not the stock of the moment,
     * says what was available.
     */
    private function cookWhatIsAvailable(StoredEvent $event, OrderToCook $order): void
    {
        $lines = $this->dishes->linesFor($order->lines, $event->position);

        if ($lines->available !== []) {
            $this->bus->dispatch($this->ticketCommand($order, $lines));
        }

        if ($lines->unavailable !== []) {
            $this->bus->dispatch(new DeclareLinesUnavailable($order->ticketId, $order->partyRef, $event->position, $lines->unavailable));
        }
    }

    private function ticketCommand(OrderToCook $order, LinesAvailability $lines): OpenKitchenTicket|AddDishesToTicket
    {
        return $order instanceof FrontOfHouseTableOrderPlaced
            ? new OpenKitchenTicket($order->ticketId, $order->partyRef, $order->tableLabel, $lines->available)
            : new AddDishesToTicket($order->ticketId, $order->partyRef, $order->tableLabel, $lines->available);
    }
}
