<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Query;

use App\Providers\BoundedContexts\SharedKernel\Application\Query;

final readonly class GetBill implements Query
{
    public function __construct(public string $coverRef) {}
}
