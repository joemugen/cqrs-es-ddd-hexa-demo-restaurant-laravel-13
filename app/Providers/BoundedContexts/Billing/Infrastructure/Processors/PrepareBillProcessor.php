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
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;

/**
 * Opens one bill from the dishes that have already left the pass.
 *
 * It depends on the till's ledger of served dishes. It reads its events first, then brings the ledger up to date,
 * so every dish served before a bill request is on the till whatever order a worker wakes the two consumers in.
 */
final class PrepareBillProcessor implements Processor
{
    public const CHECKPOINT = 'billing.prepare';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private BillingCommandBus $bus,
        private RecordServedDishes $ledger,
    ) {}

    public function hasWork(): bool
    {
        return $this->events->hasEventsAfter($this->checkpoints->get(self::CHECKPOINT));
    }

    public function consume(): int
    {
        $handled = 0;
        $position = $this->checkpoints->get(self::CHECKPOINT);

        $events = $this->events->readAllFrom($position);
        $this->ledger->consume();

        foreach ($events as $event) {
            if ($event->type->is(ConsumedEvents::FRONT_OF_HOUSE_BILL_REQUESTED)) {
                $handled++;
                Correlation::resume($event->correlationId);
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
