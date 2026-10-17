<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

/**
 * @template TQuery of Query
 */
interface QueryHandler
{
    /**
     * @param  TQuery  $query
     * @return array<array-key, mixed>
     */
    public function handle(Query $query): array;
}
