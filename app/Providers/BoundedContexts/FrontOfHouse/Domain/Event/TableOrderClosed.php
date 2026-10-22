<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain\Event;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * The guests of the visit paid and left: nothing of this order will be cooked or served any more.
 */
final readonly class TableOrderClosed implements TypedDomainEvent
{
    public function __construct(
        public TableOrderId $tableOrderId,
        public TableId $tableId,
        public VisitId $visitId,
    ) {}
}
