<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application;

use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;

interface BillRepository
{
    public function exists(BillId $id): bool;

    public function get(BillId $id): Bill;

    public function save(Bill $bill): void;

    public function openFor(CoverRef $coverRef): ?Bill;

    /**
     * The last bill of the cover, open or paid.
     */
    public function latestFor(CoverRef $coverRef): ?Bill;
}
