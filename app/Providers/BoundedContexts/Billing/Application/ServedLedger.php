<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;

interface ServedLedger
{
    /**
     * @param  int  $position  where the dish was served in the log
     */
    public function record(CoverRef $coverRef, ServedDish $dish, int $position): void;

    /**
     * What was served to a cover and is not on a bill, up to the position of the request of this one.
     *
     * @return list<ServedDish>
     */
    public function unbilled(CoverRef $coverRef, int $upToPosition): array;

    /**
     * @param  list<SourceKey>  $sourceKeys
     */
    public function attach(BillId $billId, array $sourceKeys): void;
}
