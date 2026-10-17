<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;

interface ServedLedger
{
    public function record(CoverRef $coverRef, ServedDish $dish): void;

    /**
     * @return list<ServedDish>
     */
    public function unbilled(CoverRef $coverRef): array;

    /**
     * @param  list<SourceKey>  $sourceKeys
     */
    public function attach(BillId $billId, array $sourceKeys): void;
}
