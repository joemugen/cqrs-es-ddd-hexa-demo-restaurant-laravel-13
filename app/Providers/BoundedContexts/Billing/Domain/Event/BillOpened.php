<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain\Event;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * The ledger placed the dishes served so far on a new bill: the bill must be opened with them.
 */
final readonly class BillOpened implements TypedDomainEvent
{
    /**
     * @param  list<ServedDish>  $dishes
     */
    public function __construct(
        public BillId $billId,
        public CoverRef $coverRef,
        public array $dishes,
    ) {}
}
