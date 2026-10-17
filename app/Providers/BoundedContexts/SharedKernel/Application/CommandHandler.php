<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

/**
 * @template TCommand of Command
 * @template TResponse of CommandResponse
 */
interface CommandHandler
{
    /**
     * @param  TCommand  $command
     * @return TResponse|null null when there was nothing to acknowledge
     */
    public function handle(Command $command): ?CommandResponse;
}
