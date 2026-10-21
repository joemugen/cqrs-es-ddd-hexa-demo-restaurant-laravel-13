<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The dining room placed an order: follow it until the kitchen answers.
 */
final readonly class BeginOrderFulfilment implements Command
{
    public function __construct(
        public TableOrderId $tableOrderId,
        public TableId $tableId,
        public int $requestPosition,
    ) {}
}
