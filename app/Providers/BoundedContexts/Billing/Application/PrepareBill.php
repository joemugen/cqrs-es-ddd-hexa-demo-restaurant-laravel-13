<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application;

final readonly class PrepareBill
{
    public function __construct(
        public string $billId,
        public string $coverRef,
    ) {}
}
