<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The bill of a table is paid: the guests left, and an order still waiting for a revision will never get one.
 */
final readonly class CloseOrdersOfPaidTable implements Command
{
    public function __construct(
        public TableId $tableId,
        public int $paymentPosition,
    ) {}
}
