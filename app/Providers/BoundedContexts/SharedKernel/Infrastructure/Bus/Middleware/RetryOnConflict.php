<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\Middleware;

use App\Providers\BoundedContexts\SharedKernel\Application\Bus\CommandMiddleware;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandResponse;
use App\Providers\BoundedContexts\SharedKernel\Domain\ConcurrencyConflict;
use Closure;

/**
 * Replays a command whose aggregate was changed by someone else meanwhile: the handler reads it again and decides again.
 * It must sit outside the transaction, so each attempt gets a fresh one. After the last attempt the 409 goes through.
 */
final readonly class RetryOnConflict implements CommandMiddleware
{
    public function __construct(private int $attempts = 3) {}

    public function handle(Command $command, Closure $next): ?CommandResponse
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $next($command);
            } catch (ConcurrencyConflict $conflict) {
                if ($attempt >= $this->attempts) {
                    throw $conflict;
                }
            }
        }
    }
}
