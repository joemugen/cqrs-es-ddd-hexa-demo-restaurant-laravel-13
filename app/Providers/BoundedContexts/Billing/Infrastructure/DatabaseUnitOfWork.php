<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure;

use App\Providers\BoundedContexts\Billing\Application\UnitOfWork;
use Illuminate\Support\Facades\DB;

final class DatabaseUnitOfWork implements UnitOfWork
{
    public function execute(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
