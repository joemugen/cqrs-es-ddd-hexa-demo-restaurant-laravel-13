<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware;

use App\Providers\Modules\SharedKernel\Application\Bus\QueryMiddleware;
use App\Providers\Modules\SharedKernel\Application\Query;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;
use Closure;

/**
 * Checks the #[Authorize] abilities of a query. A query that declares none is let through.
 */
final class AuthorizationQueryMiddleware implements QueryMiddleware
{
    use ChecksAbilities;

    public function handle(Query $query, Closure $next): QueryResponse
    {
        $this->authorize($query);

        return $next($query);
    }
}
