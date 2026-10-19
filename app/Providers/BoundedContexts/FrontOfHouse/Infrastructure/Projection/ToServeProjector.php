<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Projection;

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\BoundedContexts\SharedKernel\Application\Consumption\RunsOnQueue;
use App\Providers\BoundedContexts\SharedKernel\Application\Projector;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use Illuminate\Support\Facades\DB;

/**
 * Level 2: a queued projector. The pass can lag behind the kitchen.
 */
#[RunsOnQueue(ProjectToServeList::class)]
final class ToServeProjector implements Projector
{
    public const CHECKPOINT = 'foh.to-serve';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
    ) {}

    public function consume(): int
    {
        $applied = 0;
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($event->type->is(ConsumedEvents::KITCHEN_DISH_READY)) {
                DB::table(Tables::TO_SERVE)->insertOrIgnore([
                    'ticket_id' => $event->payload['ticketId'],
                    'line_id' => $event->payload['lineId'],
                    'table_id' => $event->payload['partyRef'],
                    'table_label' => $event->payload['tableLabel'],
                    'dish_name' => $event->payload['dishName'],
                    'quantity' => $event->payload['quantity'],
                ]);
                $applied++;
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $applied;
    }
}
