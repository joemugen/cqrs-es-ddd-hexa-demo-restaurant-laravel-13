<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

/**
 * @template TQuery of Query
 * @template TResponse of QueryResponse
 */
interface QueryHandler
{
    /**
     * @param  TQuery  $query
     * @return TResponse
     */
    public function handle(Query $query): QueryResponse;
}
