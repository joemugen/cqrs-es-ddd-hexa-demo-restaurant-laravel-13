<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain\Event;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * The guests of the visit are gone: the visit is over, and nothing can be ordered for it any more.
 */
final readonly class TableFreed implements TypedDomainEvent
{
    public function __construct(
        public TableId $tableId,
        public VisitId $visitId,
    ) {}
}
