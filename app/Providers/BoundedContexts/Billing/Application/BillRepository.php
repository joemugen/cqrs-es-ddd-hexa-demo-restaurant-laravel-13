<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application;

use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;

interface BillRepository
{
    public function get(BillId $id): Bill;

    public function save(Bill $bill): void;

    public function openFor(CoverRef $coverRef): ?Bill;
}
