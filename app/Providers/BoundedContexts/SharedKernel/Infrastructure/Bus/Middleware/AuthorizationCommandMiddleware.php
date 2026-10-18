<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\Middleware;

use App\Providers\BoundedContexts\SharedKernel\Application\Bus\CommandMiddleware;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandResponse;
use Closure;

/**
 * Checks the #[Authorize] abilities of a command. A command that declares none is let through.
 */
final class AuthorizationCommandMiddleware implements CommandMiddleware
{
    use ChecksAbilities;

    public function handle(Command $command, Closure $next): ?CommandResponse
    {
        $this->authorize($command);

        return $next($command);
    }
}
