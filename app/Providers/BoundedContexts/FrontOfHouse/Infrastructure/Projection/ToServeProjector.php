<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Projection;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\FrontOfHouseDishServed;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\FrontOfHouseTableOrderClosed;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed\KitchenDishReady;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\Modules\EventStore\Application\ConsumedMessages;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Consumption\RunsOnQueue;
use App\Providers\Modules\SharedKernel\Application\RebuildableProjector;
use Illuminate\Support\Facades\DB;

/**
 * Level 2: a queued projector. The list to serve is a reading of the pass: it can lag behind the kitchen, and it can be
 * rebuilt from the log. It decides nothing, the pass does.
 */
#[RunsOnQueue(ProjectToServeList::class)]
final class ToServeProjector implements RebuildableProjector
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
                    'id' => (string) DishAtThePassId::forLine($message->tableOrderId, $message->lineId),
                    'position' => $event->position,
                    'table_order_id' => (string) $message->tableOrderId,
                    'line_id' => (string) $message->lineId,
                    'table_id' => (string) $message->tableId,
                    'table_label' => (string) $message->tableLabel,
                    'dish_name' => (string) $message->dishName,
                    'quantity' => $message->quantity,
                ]);
                $applied++;
            }

            if ($message instanceof FrontOfHouseDishServed) {
                DB::table(Tables::TO_SERVE)->where('id', (string) $message->dishAtThePassId)->delete();
                $applied++;
            }

            if ($message instanceof FrontOfHouseTableOrderClosed) {
                DB::table(Tables::TO_SERVE)->where('table_order_id', (string) $message->tableOrderId)->delete();
                $applied++;
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $applied;
    }

    public function rebuild(): void
    {
        DB::table(Tables::TO_SERVE)->delete();
        $this->checkpoints->put(self::CHECKPOINT, 0);
        $this->consume();
    }
}
