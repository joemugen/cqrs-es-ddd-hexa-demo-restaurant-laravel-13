<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

/**
 * An aggregate rebuilt from its own history, which records the facts it has not yet committed.
 */
interface EventSourcedAggregate extends AggregateRoot
{
    public static function fromHistory(AggregateId $id, DomainEvents $history): self;

    public function streamId(): StreamId;

    public function expectedVersion(): int;

    public function pendingEvents(): DomainEvents;

    public function commit(int $version): void;
}
