<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application\Bus;

use App\Providers\BoundedContexts\SharedKernel\Application\Query;
use App\Providers\BoundedContexts\SharedKernel\Application\QueryResponse;
use Closure;

interface QueryMiddleware
{
    /**
     * @param  Closure(Query): QueryResponse  $next
     */
    public function handle(Query $query, Closure $next): QueryResponse;
}
