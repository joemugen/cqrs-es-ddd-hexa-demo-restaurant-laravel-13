<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application\Bus;

use App\Providers\BoundedContexts\SharedKernel\Application\Query;
use App\Providers\BoundedContexts\SharedKernel\Application\QueryResponse;

interface QueryBus
{
    public function ask(Query $query): QueryResponse;
}
