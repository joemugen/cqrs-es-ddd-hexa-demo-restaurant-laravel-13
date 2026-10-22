<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain\Event;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * The dining room placed an order for a table.
 */
final readonly class TableOrderPlaced implements TypedDomainEvent
{
    /**
     * @param  list<OrderLine>  $lines
     */
    public function __construct(
        public TableOrderId $tableOrderId,
        public TableId $tableId,
        public VisitId $visitId,
        public TableLabel $tableLabel,
        public array $lines,
    ) {}
}
