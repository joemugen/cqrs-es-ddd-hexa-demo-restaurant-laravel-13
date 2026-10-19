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
     * @return int how many events it handled
     */
    public function consume(): int;
}
