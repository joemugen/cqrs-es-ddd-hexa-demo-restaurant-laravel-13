<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The bill of the cover was paid: the ledger knows that a dish served from now on is on no bill.
 */
final readonly class SettleBill implements Command
{
    public function __construct(
        public CoverRef $coverRef,
        public BillId $billId,
    ) {}
}
