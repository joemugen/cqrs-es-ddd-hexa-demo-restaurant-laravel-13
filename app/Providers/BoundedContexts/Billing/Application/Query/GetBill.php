<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Query;

use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\Modules\SharedKernel\Application\Query;

final readonly class GetBill implements Query
{
    public function __construct(public CoverRef $coverRef) {}
}
