<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The kitchen says that a dish is ready: it waits at the pass for the room to take it.
 */
final readonly class PutDishAtThePass implements Command
{
    public function __construct(
        public TableOrderId $tableOrderId,
        public LineId $lineId,
        public TableId $tableId,
        public DishName $dishName,
        public int $quantity,
    ) {}
}
