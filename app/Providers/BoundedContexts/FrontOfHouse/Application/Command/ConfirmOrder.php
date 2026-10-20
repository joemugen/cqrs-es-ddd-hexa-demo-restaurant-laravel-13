<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The kitchen opened a ticket for the order.
 */
final readonly class ConfirmOrder implements Command
{
    public function __construct(
        public TableOrderId $tableOrderId,
    ) {}
}
