<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

/**
 * An aggregate rebuilt from its own history, which records the facts it has not yet committed.
 */
interface EventSourcedAggregate extends AggregateRoot
{
    /**
     * @param  list<array{type: string, payload: array<string, mixed>}>  $history
     */
    public static function fromHistory(string $id, array $history): self;

    public function streamId(): string;

    public function expectedVersion(): int;

    /**
     * @return list<array{type: string, payload: array<string, mixed>, occurredAt: string}>
     */
    public function pendingEvents(): array;

    public function commit(int $version): void;
}
