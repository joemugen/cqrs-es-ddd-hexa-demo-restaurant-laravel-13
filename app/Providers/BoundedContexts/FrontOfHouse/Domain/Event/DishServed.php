<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain\Event;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TicketId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * A dish left the pass for a table.
 */
final readonly class DishServed implements TypedDomainEvent
{
    public function __construct(
        public TableId $tableId,
        public VisitId $visitId,
        public DishName $dishName,
        public int $quantity,
        public int $unitPriceCents,
        public string $kind,
        public TicketId $ticketId,
        public LineId $lineId,
    ) {}
}
