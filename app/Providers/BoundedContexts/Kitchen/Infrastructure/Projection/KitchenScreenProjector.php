<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Projection;

use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishesAdded;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishFired;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishReady;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\TicketCancelled;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\TicketCompleted;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\TicketOpened;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Tables;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\RebuildableProjector;
use App\Providers\Modules\SharedKernel\Domain\EventType;
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

    public function hasWork(): bool
    {
        return $this->events->hasEventsAfter($this->checkpoints->get(self::CHECKPOINT));
    }

    public function consume(): int
    {
        $applied = 0;
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            if ($this->when($event->type, $event->payload, $event->position, $event->version)) {
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
     * @param  int  $position  where the event sits in the log, which is what orders the tickets on the screen
     * @param  int  $version  where it sits in the stream of its ticket: the version a client sends back as If-Match
     * @return bool whether the event changed the screen
     */
    public function when(EventType $type, array $payload, int $position, int $version): bool
    {
        if ($type->is(TicketOpened::STORED_AS)) {
            DB::table(Tables::SCREEN_TICKETS)->insert([
                'ticket_id' => $payload['ticketId'],
                'party_ref' => $payload['partyRef'],
                'table_label' => $payload['tableLabel'],
                'done' => false,
                'opened_position' => $position,
                'version' => $version,
            ]);

            $this->insertLines((string) $payload['ticketId'], $payload['lines']);

            return true;
        }

        if ($type->is(DishesAdded::STORED_AS)) {
            $this->insertLines((string) $payload['ticketId'], $payload['lines']);
            DB::table(Tables::SCREEN_TICKETS)
                ->where('ticket_id', $payload['ticketId'])
                ->update(['done' => false, 'version' => $version]);

            return true;
        }

        if ($type->is(DishFired::STORED_AS)) {
            $this->status((string) $payload['ticketId'], (string) $payload['lineId'], LineStatus::FIRED);
            $this->versionOf((string) $payload['ticketId'], $version);

            return true;
        }

        if ($type->is(DishReady::STORED_AS)) {
            $this->status((string) $payload['ticketId'], (string) $payload['lineId'], LineStatus::READY);
            $this->versionOf((string) $payload['ticketId'], $version);

            return true;
        }

        if ($type->is(TicketCompleted::STORED_AS)) {
            DB::table(Tables::SCREEN_TICKETS)
                ->where('ticket_id', $payload['ticketId'])
                ->update(['done' => true, 'version' => $version]);

            return true;
        }

        if ($type->is(TicketCancelled::STORED_AS)) {
            DB::table(Tables::SCREEN_LINES)->where('ticket_id', $payload['ticketId'])->delete();
            DB::table(Tables::SCREEN_TICKETS)->where('ticket_id', $payload['ticketId'])->delete();

            return true;
        }

        return false;
    }

    /**
     * @param  list<array{lineId: string, dishName: string, station: string, cookingMinutes: int, quantity: int, course: string, allergens: list<string>}>  $lines
     */
    private function insertLines(string $ticketId, array $lines): void
    {
        foreach ($lines as $line) {
            DB::table(Tables::SCREEN_LINES)->insert([
                'ticket_id' => $ticketId,
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
    }

    private function versionOf(string $ticketId, int $version): void
    {
        DB::table(Tables::SCREEN_TICKETS)->where('ticket_id', $ticketId)->update(['version' => $version]);
    }

    private function status(string $ticketId, string $lineId, string $status): void
    {
        DB::table(Tables::SCREEN_LINES)
            ->where('ticket_id', $ticketId)
            ->where('line_id', $lineId)
            ->update(['status' => $status]);
    }
}
