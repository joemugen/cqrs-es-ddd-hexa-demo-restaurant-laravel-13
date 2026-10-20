<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware;

use App\Providers\Modules\SharedKernel\Application\Bus\QueryMiddleware;
use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;
use App\Providers\Modules\SharedKernel\Application\Query;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\MeasuredCall;
use Closure;

/**
 * Counts every query by type and outcome, with its duration.
 */
final readonly class MetricsQueryMiddleware implements QueryMiddleware
{
    public function __construct(private MeasuredCall $measured) {}

    public function handle(Query $query, Closure $next): QueryResponse
    {
        return $this->measured->run(Metric::Query, $query::class, fn (): QueryResponse => $next($query));
    }
}
