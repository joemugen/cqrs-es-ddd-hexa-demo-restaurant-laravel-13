<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application;

use App\Providers\BoundedContexts\Billing\Domain\CoverLedger;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\Modules\SharedKernel\Application\Concurrency\ConcurrencyConflict;

interface CoverLedgerRepository
{
    /**
     * The ledger of a cover: an empty one while nothing was served to it.
     */
    public function get(CoverRef $coverRef): CoverLedger;

    /**
     * @throws ConcurrencyConflict when somebody saved the ledger since it was read
     */
    public function save(CoverLedger $ledger): void;
}
