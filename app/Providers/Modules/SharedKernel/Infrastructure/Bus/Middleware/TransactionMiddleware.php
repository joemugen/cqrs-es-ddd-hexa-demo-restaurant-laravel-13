<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware;

use App\Providers\Modules\SharedKernel\Application\Bus\CommandMiddleware;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * One command, one transaction: what the handler writes commits together or not at all.
 * Consumers woken by the event store run after this commit, never inside it.
 */
final class TransactionMiddleware implements CommandMiddleware
{
    public function handle(Command $command, Closure $next): ?CommandResponse
    {
        return DB::transaction(fn (): ?CommandResponse => $next($command));
    }
}
