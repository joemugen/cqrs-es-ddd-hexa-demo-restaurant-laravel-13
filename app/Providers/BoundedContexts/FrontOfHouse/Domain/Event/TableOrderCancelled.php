<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain\Event;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * The guests gave up the dishes that were unavailable.
 */
final readonly class TableOrderCancelled implements TypedDomainEvent
{
    /**
     * @param  list<LineId>  $abandonedLines  the lines that were still unavailable
     */
    public function __construct(
        public TableOrderId $tableOrderId,
        public TableId $tableId,
        public string $reason,
        public array $abandonedLines,
    ) {}
}
