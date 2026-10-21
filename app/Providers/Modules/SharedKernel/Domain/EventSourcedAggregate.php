<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * An aggregate rebuilt from its own history, which records the facts it has not yet committed.
 */
interface EventSourcedAggregate extends AggregateRoot
{
    public static function fromHistory(AggregateId $id, RecordedEvents $history): self;

    public function streamId(): StreamId;

    public function expectedVersion(): int;

    public function pendingEvents(): RecordedEvents;

    public function commit(int $version): void;
}
