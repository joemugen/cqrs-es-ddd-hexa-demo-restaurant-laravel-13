<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The ledger placed these dishes on a new bill: the bill is opened with them, once.
 */
final readonly class OpenBill implements Command
{
    /**
     * @param  list<ServedDish>  $dishes
     */
    public function __construct(
        public BillId $billId,
        public CoverRef $coverRef,
        public array $dishes,
    ) {}
}
