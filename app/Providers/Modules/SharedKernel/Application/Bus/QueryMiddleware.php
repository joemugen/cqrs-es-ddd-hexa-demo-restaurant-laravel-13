<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Bus;

use App\Providers\Modules\SharedKernel\Application\Query;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;
use Closure;

interface QueryMiddleware
{
    /**
     * @param  Closure(Query): QueryResponse  $next
     */
    public function handle(Query $query, Closure $next): QueryResponse;
}
