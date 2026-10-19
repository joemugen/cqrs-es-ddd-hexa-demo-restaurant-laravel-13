<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Processors;

use App\Providers\BoundedContexts\Billing\Application\Command\PrepareBill;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Infrastructure\Bus\BillingCommandBus;
use App\Providers\BoundedContexts\Billing\Infrastructure\ConsumedEvents;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Processor;

/**
 * Opens one bill from the dishes that have already left the pass.
 */
final class PrepareBillProcessor implements Processor
{
    public const CHECKPOINT = 'billing.prepare';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private BillingCommandBus $bus,
    ) {}

    public function consume(): int
    {
        $handled = 0;
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($event->type->is(ConsumedEvents::FRONT_OF_HOUSE_BILL_REQUESTED)) {
                $handled++;
                $this->bus->dispatch(new PrepareBill(
                    billId: BillId::forBillRequest(new CoverRef((string) $event->payload['tableId']), $event->position),
                    coverRef: new CoverRef((string) $event->payload['tableId']),
                ));
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }
}
