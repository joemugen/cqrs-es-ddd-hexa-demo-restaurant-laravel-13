<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Processors;

use App\Providers\BoundedContexts\Kitchen\Application\Command\AddDishesToTicket;
use App\Providers\BoundedContexts\Kitchen\Application\Command\DeclareLinesUnavailable;
use App\Providers\BoundedContexts\Kitchen\Application\Command\OpenKitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Application\DishCatalog;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\LinesAvailability;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenCommandBus;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\ConsumedEvents;
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
            if ($this->isAnOrderToCook($event)) {
                $handled++;
                Correlation::resume($event->correlationId);
                Causation::resume($event->position);
                $this->cookWhatIsAvailable($event);
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }

    /**
     * @param  list<array{lineId: string, name: string, quantity: int, course: string, allergens: list<string>}>  $lines
     * @return list<OrderedLine>
     */
    private function orderedLines(array $lines): array
    {
        return array_map(fn (array $line): OrderedLine => new OrderedLine(
            new LineId((string) $line['lineId']),
            new DishName((string) $line['name']),
            (int) $line['quantity'],
            (string) $line['course'],
            array_values($line['allergens']),
        ), $lines);
    }

    /**
     * What the kitchen can cook goes to the ticket at once. What it cannot is not a failure to retry: it becomes a fact
     * the dining room answers, and the orders behind go on. The position of the event, not the stock of the moment,
     * says what was available.
     */
    private function cookWhatIsAvailable(StoredEvent $event): void
    {
        $ticketId = new KitchenTicketId((string) $event->payload['tableOrderId']);
        $partyRef = new PartyRef((string) $event->payload['tableId']);
        $lines = $this->dishes->linesFor($this->orderedLines($event->payload['lines']), $event->position);

        if ($lines->available !== []) {
            $this->bus->dispatch($this->ticketCommand($event, $ticketId, $partyRef, $lines));
        }

        if ($lines->unavailable !== []) {
            $this->bus->dispatch(new DeclareLinesUnavailable($ticketId, $partyRef, $event->position, $lines->unavailable));
        }
    }

    private function ticketCommand(StoredEvent $event, KitchenTicketId $ticketId, PartyRef $partyRef, LinesAvailability $lines): OpenKitchenTicket|AddDishesToTicket
    {
        return $event->type->is(ConsumedEvents::FRONT_OF_HOUSE_TABLE_ORDER_PLACED)
            ? new OpenKitchenTicket($ticketId, $partyRef, new TableLabel((string) $event->payload['tableLabel']), $lines->available)
            : new AddDishesToTicket($ticketId, $partyRef, new TableLabel((string) $event->payload['tableLabel']), $lines->available);
    }

    private function isAnOrderToCook(StoredEvent $event): bool
    {
        return $event->type->is(ConsumedEvents::FRONT_OF_HOUSE_TABLE_ORDER_PLACED)
            || $event->type->is(ConsumedEvents::FRONT_OF_HOUSE_TABLE_ORDER_REVISED);
    }
}
