<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Processors;

use App\Providers\BoundedContexts\Billing\Application\ServedLedger;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\EventStore\Domain\CheckpointStore;
use App\Providers\BoundedContexts\EventStore\Domain\EventStore;

/**
 * Copies a served dish onto the till. It is not replayed: a charge is taken once.
 */
final class RecordServedDishes
{
    public const CHECKPOINT = 'billing.served';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private ServedLedger $served,
    ) {}

    public function run(): void
    {
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($event->type === 'front_of_house.dish_served') {
                $payload = $event->payload;
                $this->served->record((string) $payload['tableId'], new ServedDish(
                    sourceKey: (string) $payload['sourceKey'],
                    description: (string) $payload['description'],
                    quantity: (int) $payload['quantity'],
                    unitPriceCents: (int) $payload['unitPriceCents'],
                    kind: (string) $payload['kind'],
                ));
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);
    }
}
