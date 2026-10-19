<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Bus;

use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use Closure;

interface CommandMiddleware
{
    /**
     * @param  Closure(Command): ?CommandResponse  $next
     */
    public function handle(Command $command, Closure $next): ?CommandResponse;
}
