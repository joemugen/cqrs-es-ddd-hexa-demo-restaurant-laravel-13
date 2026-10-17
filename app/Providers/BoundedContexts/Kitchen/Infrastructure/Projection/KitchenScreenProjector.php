<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Projection;

use App\Providers\BoundedContexts\EventStore\Domain\CheckpointStore;
use App\Providers\BoundedContexts\EventStore\Domain\EventStore;
use Illuminate\Support\Facades\DB;

/**
 * Folds the ticket stream into the pass screen. It decides nothing.
 */
final class KitchenScreenProjector
{
    public const CHECKPOINT = 'kitchen.screen';

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
    ) {}

    public function catchUp(): void
    {
        $position = $this->checkpoints->get(self::CHECKPOINT);

        foreach ($this->events->readAllFrom($position) as $event) {
            $this->when($event->type, $event->payload);
            $position = $event->position;
        }

        $this->checkpoints->put(self::CHECKPOINT, $position);
    }

    public function rebuild(): void
    {
        DB::table('kitchen_screen_lines')->delete();
        DB::table('kitchen_screen_tickets')->delete();
        $this->checkpoints->put(self::CHECKPOINT, 0);
        $this->catchUp();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function when(string $type, array $payload): void
    {
        if ($type === 'kitchen.ticket_opened') {
            DB::table('kitchen_screen_tickets')->insert([
                'ticket_id' => $payload['ticketId'],
                'party_ref' => $payload['partyRef'],
                'table_label' => $payload['tableLabel'],
                'done' => false,
            ]);

            foreach ($payload['lines'] as $line) {
                DB::table('kitchen_screen_lines')->insert([
                    'ticket_id' => $payload['ticketId'],
                    'line_id' => $line['lineId'],
                    'dish_name' => $line['dishName'],
                    'station' => $line['station'],
                    'cooking_minutes' => $line['cookingMinutes'],
                    'quantity' => $line['quantity'],
                    'course' => $line['course'],
                    'allergens' => json_encode($line['allergens'], JSON_THROW_ON_ERROR),
                    'status' => 'pending',
                ]);
            }

            return;
        }

        if ($type === 'kitchen.dish_fired') {
            $this->status((string) $payload['ticketId'], (string) $payload['lineId'], 'fired');

            return;
        }

        if ($type === 'kitchen.dish_ready') {
            $this->status((string) $payload['ticketId'], (string) $payload['lineId'], 'ready');

            return;
        }

        if ($type === 'kitchen.ticket_completed') {
            DB::table('kitchen_screen_tickets')
                ->where('ticket_id', $payload['ticketId'])
                ->update(['done' => true]);
        }
    }

    private function status(string $ticketId, string $lineId, string $status): void
    {
        DB::table('kitchen_screen_lines')
            ->where('ticket_id', $ticketId)
            ->where('line_id', $lineId)
            ->update(['status' => $status]);
    }
}
