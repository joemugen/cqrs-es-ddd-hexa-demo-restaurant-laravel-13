<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Processors;

use App\Providers\BoundedContexts\EventStore\Domain\CheckpointStore;
use App\Providers\BoundedContexts\EventStore\Domain\EventStore;
use App\Providers\BoundedContexts\Kitchen\Application\Command\OpenKitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Application\Command\OpenKitchenTicketHandler;
use App\Providers\BoundedContexts\Kitchen\Application\DishCatalog;
use App\Providers\BoundedContexts\SharedKernel\Application\Processor;

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
        private OpenKitchenTicketHandler $handler,
    ) {}

    public function run(): void
    {
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($event->type === 'front_of_house.table_order_placed') {
                $this->handler->handle(new OpenKitchenTicket(
                    ticketId: (string) $event->payload['tableOrderId'],
                    partyRef: (string) $event->payload['tableId'],
                    tableLabel: (string) $event->payload['tableLabel'],
                    lines: $this->dishes->linesFor($event->payload['lines']),
                ));
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);
    }
}
