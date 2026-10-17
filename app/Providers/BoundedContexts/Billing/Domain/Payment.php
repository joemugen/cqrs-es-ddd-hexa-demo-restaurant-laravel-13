<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

final readonly class Payment
{
    public function __construct(
        public int $amountCents,
        public int $tipCents,
    ) {}
}
