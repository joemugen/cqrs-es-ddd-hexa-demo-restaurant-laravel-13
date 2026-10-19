<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Domain;

use App\Providers\Modules\SharedKernel\Domain\DomainEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamId;

interface EventStore
{
    /**
     * Append facts to a stream if nobody else wrote since $expectedVersion.
     *
     * Version 0 means the stream does not exist yet. A stale version throws.
     *
     * @return int the position in the log of the last fact written, 0 when there was none
     */
    public function append(StreamId $streamId, int $expectedVersion, DomainEvents $events): int;

    /**
     * Whether any event sits past $position. A read that does not load the events.
     */
    public function hasEventsAfter(int $position): bool;

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
