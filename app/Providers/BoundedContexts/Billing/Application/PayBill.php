<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application;

final readonly class PayBill
{
    public function __construct(
        public string $billId,
        public int $amountCents,
        public int $tipCents,
    ) {}
}
