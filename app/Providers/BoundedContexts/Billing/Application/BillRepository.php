<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application;

use App\Providers\BoundedContexts\Billing\Domain\Bill;

interface BillRepository
{
    public function get(string $id): Bill;

    public function save(Bill $bill): void;

    public function openFor(string $coverRef): ?Bill;
}
