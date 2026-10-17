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
     * @return array<string, mixed>
     */
    public function handle(Command $command): array;
}
