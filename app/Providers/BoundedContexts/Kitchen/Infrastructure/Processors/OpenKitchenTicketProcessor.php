<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Processors;

use App\Providers\BoundedContexts\Kitchen\Application\Command\OpenKitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Application\Command\RefuseOrder;
use App\Providers\BoundedContexts\Kitchen\Application\DishCatalog;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
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
 * A processor reacts once. It is not a projection: replaying it would open tickets again.
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
            if ($event->type->is(ConsumedEvents::FRONT_OF_HOUSE_TABLE_ORDER_PLACED)) {
                $handled++;
                Correlation::resume($event->correlationId);
                Causation::resume($event->position);
                $this->openOrRefuse($event);
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
     * An order the kitchen cannot cook is not a failure to retry: it is a business outcome, so it becomes a fact
     * the dining room can compensate for, and the orders behind it go on.
     */
    private function openOrRefuse(StoredEvent $event): void
    {
        $ticketId = new KitchenTicketId((string) $event->payload['tableOrderId']);
        $partyRef = new PartyRef((string) $event->payload['tableId']);

        try {
            $lines = $this->dishes->linesFor($this->orderedLines($event->payload['lines']), $event->position);
        } catch (KitchenException $refusal) {
            $this->bus->dispatch(new RefuseOrder($ticketId, $partyRef, $refusal->getMessage()));

            return;
        }

        $this->bus->dispatch(new OpenKitchenTicket(
            ticketId: $ticketId,
            partyRef: $partyRef,
            tableLabel: new TableLabel((string) $event->payload['tableLabel']),
            lines: $lines,
        ));
    }
}
