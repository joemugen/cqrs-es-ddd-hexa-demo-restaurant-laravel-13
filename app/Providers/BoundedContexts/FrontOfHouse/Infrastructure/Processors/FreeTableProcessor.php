<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Processors;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\FreeTable;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Processor;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;

/**
 * The dining room hears that the bill is paid and clears the table. Once.
 */
final class FreeTableProcessor implements Processor
{
    public const CHECKPOINT = 'foh.free-table';

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
            if ($event->type->is(ConsumedEvents::BILLING_BILL_PAID)) {
                $handled++;
                Correlation::resume($event->correlationId);
                $this->bus->dispatch(new FreeTable(new TableId((string) $event->payload['coverRef'])));
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }
}
