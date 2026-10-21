<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Projection;

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenDishReady;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\Modules\EventStore\Application\ConsumedMessages;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Consumption\RunsOnQueue;
use App\Providers\Modules\SharedKernel\Application\Projector;
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
        private ConsumedMessages $messages,
    ) {}

    public function hasWork(): bool
    {
        return $this->events->hasEventsAfter($this->checkpoints->get(self::CHECKPOINT));
    }

    public function consume(): int
    {
        $applied = 0;
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            $message = $this->messages->read($event, self::class);

            if ($message instanceof KitchenDishReady) {
                DB::table(Tables::TO_SERVE)->insertOrIgnore([
                    'ticket_id' => (string) $message->ticketId,
                    'line_id' => (string) $message->lineId,
                    'table_id' => (string) $message->tableId,
                    'table_label' => (string) $message->tableLabel,
                    'dish_name' => (string) $message->dishName,
                    'quantity' => $message->quantity,
                ]);
                $applied++;
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $applied;
    }
}
