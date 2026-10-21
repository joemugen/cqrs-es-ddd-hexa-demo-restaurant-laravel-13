<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain\Event;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * The guests chose replacements: these lines join the order.
 */
final readonly class TableOrderRevised implements TypedDomainEvent
{
    /**
     * @param  list<OrderLine>  $addedLines
     */
    public function __construct(
        public TableOrderId $tableOrderId,
        public TableId $tableId,
        public TableLabel $tableLabel,
        public array $addedLines,
    ) {}
}
