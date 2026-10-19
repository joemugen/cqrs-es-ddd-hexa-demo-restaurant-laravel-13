<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Bus;

use App\Providers\Modules\SharedKernel\Application\Query;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;

interface QueryBus
{
    public function ask(Query $query): QueryResponse;
}
