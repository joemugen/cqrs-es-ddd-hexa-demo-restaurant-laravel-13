<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

/**
 * @template TCommand of Command
 */
interface CommandHandler
{
    /**
     * @param  TCommand  $command
     * @return array<string, mixed>|null null when there was nothing to acknowledge
     */
    public function handle(Command $command): ?array;
}
