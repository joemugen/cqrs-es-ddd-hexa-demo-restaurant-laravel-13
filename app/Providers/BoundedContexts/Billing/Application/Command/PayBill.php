<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\SharedKernel\Application\Command;

final readonly class PayBill implements Command
{
    public function __construct(
        public string $billId,
        public int $amountCents,
        public int $tipCents,
    ) {}
}
