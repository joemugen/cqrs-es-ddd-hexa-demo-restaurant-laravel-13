<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\Concurrency\ExpectedVersion;

final readonly class PayBill implements Command
{
    public function __construct(
        public BillId $billId,
        public int $amountCents,
        public int $tipCents,
        public ExpectedVersion $seen,
    ) {}
}
