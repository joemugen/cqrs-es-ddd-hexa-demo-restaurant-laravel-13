<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Domain;

interface EventStore
{
    /**
     * Append facts to a stream if nobody else wrote since $expectedVersion.
     *
     * Version 0 means the stream does not exist yet. A stale version throws.
     *
     * @param  list<EventMessage>  $events
     */
    public function append(string $streamId, int $expectedVersion, array $events): void;

    /**
     * @return list<StoredEvent>
     */
    public function readStream(string $streamId): array;

    /**
     * Events whose global position is greater than $position, oldest first.
     *
     * @return list<StoredEvent>
     */
    public function readAllFrom(int $position): array;
}
