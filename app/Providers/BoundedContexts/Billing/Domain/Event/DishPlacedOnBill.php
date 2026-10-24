<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain\Event;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * A dish came out while the bill of its cover was open, as far as the ledger knows: the bill must receive it.
 */
final readonly class DishPlacedOnBill implements TypedDomainEvent
{
    public function __construct(
        public BillId $billId,
        public CoverRef $coverRef,
        public ServedDish $dish,
    ) {}
}
