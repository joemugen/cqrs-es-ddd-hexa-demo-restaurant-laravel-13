<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\Modules\SharedKernel\Application\Command;

final readonly class PrepareBill implements Command
{
    public function __construct(
        public BillId $billId,
        public CoverRef $coverRef,
    ) {}
}
