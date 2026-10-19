<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application;

/**
 * Reads the facts appended since its checkpoint and reacts to the ones it cares about.
 * Whoever wakes it, an in-process listener or a worker, calls the same method.
 */
interface EventConsumer
{
    /**
     * A cheap read, with no lock and no write: is there an event past my checkpoint?
     * A worker asks before it opens a transaction, so waiting costs the database nothing.
     */
    public function hasWork(): bool;

    /**
     * @return int how many events it handled
     */
    public function consume(): int;
}
