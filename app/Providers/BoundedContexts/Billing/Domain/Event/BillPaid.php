<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain\Event;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * The balance of a bill reached zero: the guests of the cover may leave.
 */
final readonly class BillPaid implements TypedDomainEvent
{
    public function __construct(
        public BillId $billId,
        public CoverRef $coverRef,
    ) {}
}
