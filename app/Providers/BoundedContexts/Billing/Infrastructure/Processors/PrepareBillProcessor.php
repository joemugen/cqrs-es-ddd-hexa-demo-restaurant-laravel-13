<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Processors;

use App\Providers\BoundedContexts\Billing\Application\Command\PrepareBill;
use App\Providers\BoundedContexts\Billing\Application\Command\PrepareBillHandler;
use App\Providers\BoundedContexts\EventStore\Domain\CheckpointStore;
use App\Providers\BoundedContexts\EventStore\Domain\EventStore;
use App\Providers\BoundedContexts\SharedKernel\Application\Processor;
use Illuminate\Support\Str;

/**
 * Opens one bill from the dishes that have already left the pass.
 */
final class PrepareBillProcessor implements Processor
{
    public const CHECKPOINT = 'billing.prepare';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private PrepareBillHandler $handler,
    ) {}

    public function run(): void
    {
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($event->type->is('front_of_house.bill_requested')) {
                $this->handler->handle(new PrepareBill(
                    billId: (string) Str::ulid(),
                    coverRef: (string) $event->payload['tableId'],
                ));
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);
    }
}
