<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Domain;

use App\Providers\BoundedContexts\SharedKernel\Domain\DomainEvents;
use App\Providers\BoundedContexts\SharedKernel\Domain\StreamId;

interface EventStore
{
    /**
     * Append facts to a stream if nobody else wrote since $expectedVersion.
     *
     * Version 0 means the stream does not exist yet. A stale version throws.
     */
    public function append(StreamId $streamId, int $expectedVersion, DomainEvents $events): void;

    /**
     * @return list<StoredEvent>
     */
    public function readStream(StreamId $streamId): array;

    /**
     * Events whose global position is greater than $position, oldest first.
     *
     * @return list<StoredEvent>
     */
    public function readAllFrom(int $position): array;
}
