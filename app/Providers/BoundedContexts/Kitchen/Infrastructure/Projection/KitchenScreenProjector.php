<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Projection;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenEvent;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Tables;
use App\Providers\BoundedContexts\SharedKernel\Application\RebuildableProjector;
use App\Providers\BoundedContexts\SharedKernel\Domain\EventType;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use Illuminate\Support\Facades\DB;

/**
 * Folds the ticket stream into the kitchen screen. It decides nothing.
 */
final class KitchenScreenProjector implements RebuildableProjector
{
    public const CHECKPOINT = 'kitchen.screen';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
    ) {}

    public function consume(): int
    {
        $applied = 0;
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($this->when($event->type, $event->payload)) {
                $applied++;
            }

            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);

        return $applied;
    }

    public function rebuild(): void
    {
        DB::table(Tables::SCREEN_LINES)->delete();
        DB::table(Tables::SCREEN_TICKETS)->delete();
        $this->checkpoints->put(self::CHECKPOINT, 0);
        $this->consume();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return bool whether the event changed the screen
     */
    public function when(EventType $type, array $payload): bool
    {
        if ($type->is(KitchenEvent::TICKET_OPENED)) {
            DB::table(Tables::SCREEN_TICKETS)->insert([
                'ticket_id' => $payload['ticketId'],
                'party_ref' => $payload['partyRef'],
                'table_label' => $payload['tableLabel'],
                'done' => false,
            ]);

            foreach ($payload['lines'] as $line) {
                DB::table(Tables::SCREEN_LINES)->insert([
                    'ticket_id' => $payload['ticketId'],
                    'line_id' => $line['lineId'],
                    'dish_name' => $line['dishName'],
                    'station' => $line['station'],
                    'cooking_minutes' => $line['cookingMinutes'],
                    'quantity' => $line['quantity'],
                    'course' => $line['course'],
                    'allergens' => json_encode($line['allergens'], JSON_THROW_ON_ERROR),
                    'status' => LineStatus::PENDING,
                ]);
            }

            return true;
        }

        if ($type->is(KitchenEvent::DISH_FIRED)) {
            $this->status((string) $payload['ticketId'], (string) $payload['lineId'], LineStatus::FIRED);

            return true;
        }

        if ($type->is(KitchenEvent::DISH_READY)) {
            $this->status((string) $payload['ticketId'], (string) $payload['lineId'], LineStatus::READY);

            return true;
        }

        if ($type->is(KitchenEvent::TICKET_COMPLETED)) {
            DB::table(Tables::SCREEN_TICKETS)
                ->where('ticket_id', $payload['ticketId'])
                ->update(['done' => true]);

            return true;
        }

        return false;
    }

    private function status(string $ticketId, string $lineId, string $status): void
    {
        DB::table(Tables::SCREEN_LINES)
            ->where('ticket_id', $ticketId)
            ->where('line_id', $lineId)
            ->update(['status' => $status]);
    }
}
