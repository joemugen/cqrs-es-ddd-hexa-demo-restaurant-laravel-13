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
     * @return bool whether the dish was new: one told twice is recorded once
     */
    public function record(CoverRef $coverRef, ServedDish $dish, int $position): bool;

    /**
     * What was served to a cover and is not on a bill.
     *
     * @return list<ServedDish>
     */
    public function unbilled(CoverRef $coverRef): array;

    /**
     * The dish came out after its bill was paid: it is on no bill, and says so.
     */
    public function writeOff(SourceKey $sourceKey): void;

    /**
     * @param  list<SourceKey>  $sourceKeys
     */
    public function attach(BillId $billId, array $sourceKeys): void;
}
