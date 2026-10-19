<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Processors;

use App\Providers\BoundedContexts\Billing\Application\ServedLedger;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\BoundedContexts\Billing\Infrastructure\ConsumedEvents;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Processor;

/**
 * Copies a served dish onto the till. It is not replayed: a charge is taken once.
 */
final class RecordServedDishes implements Processor
{
    public const CHECKPOINT = 'billing.served';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private ServedLedger $served,
    ) {}

    public function consume(): int
    {
        $handled = 0;
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($event->type->is(ConsumedEvents::FRONT_OF_HOUSE_DISH_SERVED)) {
                $handled++;
                $payload = $event->payload;
                $this->served->record(new CoverRef((string) $payload['tableId']), new ServedDish(
                    sourceKey: new SourceKey((string) $payload['sourceKey']),
                    description: (string) $payload['description'],
                    quantity: (int) $payload['quantity'],
                    unitPriceCents: (int) $payload['unitPriceCents'],
                    kind: (string) $payload['kind'],
                ));
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $handled;
    }
}
