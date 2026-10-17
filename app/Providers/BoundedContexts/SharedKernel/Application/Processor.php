<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

/**
 * Reacts once to facts from the log and keeps its own checkpoint. Replaying it would repeat the reaction.
 */
interface Processor
{
    public function run(): void;
}
