<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Processors;

use App\Providers\BoundedContexts\EventStore\Domain\CheckpointStore;
use App\Providers\BoundedContexts\EventStore\Domain\EventStore;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\FreeTable;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\FreeTableHandler;
use App\Providers\BoundedContexts\SharedKernel\Application\Processor;

/**
 * The dining room hears that the bill is paid and clears the table. Once.
 */
final class FreeTableProcessor implements Processor
{
    public const CHECKPOINT = 'foh.free-table';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private FreeTableHandler $handler,
    ) {}

    public function run(): void
    {
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($event->type->is('billing.bill_paid')) {
                $this->handler->handle(new FreeTable((string) $event->payload['coverRef']));
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);
    }
}
