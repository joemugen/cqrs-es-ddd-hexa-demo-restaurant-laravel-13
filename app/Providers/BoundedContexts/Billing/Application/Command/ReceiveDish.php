<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The ledger placed a dish on this bill: the bill receives it, unless it was paid meanwhile, in which case it says so.
 */
final readonly class ReceiveDish implements Command
{
    public function __construct(
        public BillId $billId,
        public ServedDish $dish,
    ) {}
}
