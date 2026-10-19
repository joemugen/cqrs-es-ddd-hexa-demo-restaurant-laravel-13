<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Infrastructure;

use App\Providers\BoundedContexts\SharedKernel\Domain\DomainEvents;
use App\Providers\BoundedContexts\SharedKernel\Domain\EventType;
use App\Providers\BoundedContexts\SharedKernel\Domain\StreamId;
use App\Providers\Modules\EventStore\Application\AfterAppend;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\OptimisticConcurrencyException;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DatabaseEventStore implements EventStore
{
    public function __construct(private AfterAppend $afterAppend) {}

    public function append(StreamId $streamId, int $expectedVersion, DomainEvents $events): void
    {
        if ($events->isEmpty()) {
            return;
        }

        $table = (string) config('event_store.events_table');

        DB::transaction(function () use ($streamId, $expectedVersion, $events, $table): void {
            $actual = $this->version($table, $streamId);

            if ($actual !== $expectedVersion) {
                throw new OptimisticConcurrencyException($streamId, $expectedVersion, $actual);
            }

            $version = $expectedVersion;

            foreach ($events as $event) {
                $version++;

                try {
                    DB::table($table)->insert([
                        'stream_id' => (string) $streamId,
                        'version' => $version,
                        'type' => (string) $event->type,
                        'payload' => json_encode($event->payload, JSON_THROW_ON_ERROR),
                        'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s'),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    throw new OptimisticConcurrencyException(
                        $streamId,
                        $expectedVersion,
                        $this->version($table, $streamId),
                    );
                }
            }
        });

        // Bind to the writer's transaction when one is still open, so a consumer
        // cannot roll that write back. With no writer left, this runs now.
        DB::afterCommit(function (): void {
            $this->afterAppend->notify();
        });
    }

    public function readStream(StreamId $streamId): array
    {
        $rows = DB::table((string) config('event_store.events_table'))
            ->where('stream_id', (string) $streamId)
            ->orderBy('version')
            ->get();

        return $rows->map(fn (object $row): StoredEvent => $this->stored($row))->all();
    }

    public function readAllFrom(int $position): array
    {
        $rows = DB::table((string) config('event_store.events_table'))
            ->where('position', '>', $position)
            ->orderBy('position')
            ->get();

        return $rows->map(fn (object $row): StoredEvent => $this->stored($row))->all();
    }

    private function version(string $table, StreamId $streamId): int
    {
        $version = DB::table($table)->where('stream_id', (string) $streamId)->max('version');

        return $version === null ? 0 : (int) $version;
    }

    private function stored(object $row): StoredEvent
    {
        $payload = $row->payload;
        if (is_string($payload)) {
            $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }

        return new StoredEvent(
            position: (int) $row->position,
            streamId: new StreamId((string) $row->stream_id),
            version: (int) $row->version,
            type: new EventType((string) $row->type),
            payload: is_array($payload) ? $payload : [],
            occurredAt: (string) $row->occurred_at,
        );
    }
}
