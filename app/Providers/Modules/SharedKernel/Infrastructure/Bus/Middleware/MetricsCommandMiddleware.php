<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware;

use App\Providers\Modules\SharedKernel\Application\Bus\CommandMiddleware;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\MeasuredCall;
use Closure;

/**
 * Counts every command by type and outcome, with its duration. It sits outside the retry and the transaction:
 * a command is counted once as the caller sees it, and a failure is counted even though its transaction rolled back.
 */
final readonly class MetricsCommandMiddleware implements CommandMiddleware
{
    public function __construct(private MeasuredCall $measured) {}

    public function handle(Command $command, Closure $next): ?CommandResponse
    {
        return $this->measured->run(Metric::Command, $command::class, fn (): ?CommandResponse => $next($command));
    }
}
