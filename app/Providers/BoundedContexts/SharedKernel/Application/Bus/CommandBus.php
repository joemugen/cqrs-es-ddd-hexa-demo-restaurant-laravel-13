<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application\Bus;

use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandResponse;

interface CommandBus
{
    /**
     * Send a command through the middleware to its handler.
     * Null means the handler had nothing to acknowledge.
     */
    public function dispatch(Command $command): ?CommandResponse;
}
