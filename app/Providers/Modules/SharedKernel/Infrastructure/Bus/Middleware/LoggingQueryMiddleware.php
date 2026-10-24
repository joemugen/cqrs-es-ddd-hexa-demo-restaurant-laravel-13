<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware;

use App\Providers\Modules\SharedKernel\Application\Bus\QueryMiddleware;
use App\Providers\Modules\SharedKernel\Application\Exception\Refusal;
use App\Providers\Modules\SharedKernel\Application\Query;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;
use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;
use App\Providers\Modules\SharedKernel\Infrastructure\Http\RefusalStatuses;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Logs every query with its duration and the correlation id of the request, which Laravel adds to each log line.
 */
final class LoggingQueryMiddleware implements QueryMiddleware
{
    public function handle(Query $query, Closure $next): QueryResponse
    {
        Correlation::ensure();
        $start = hrtime(true);

        try {
            $response = $next($query);
        } catch (DomainException|Refusal $refusal) {
            Log::info('Query refused', $this->context($query, $start, ['reason' => $refusal->getMessage(), 'status' => app(RefusalStatuses::class)->of($refusal)]));

            throw $refusal;
        } catch (Throwable $failure) {
            Log::warning('Query failed', $this->context($query, $start, ['exception' => $failure::class]));

            throw $failure;
        }

        Log::info('Query answered', $this->context($query, $start));

        return $response;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function context(Query $query, int $start, array $extra = []): array
    {
        return [
            'query' => $query::class,
            'duration_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            ...$extra,
        ];
    }
}
