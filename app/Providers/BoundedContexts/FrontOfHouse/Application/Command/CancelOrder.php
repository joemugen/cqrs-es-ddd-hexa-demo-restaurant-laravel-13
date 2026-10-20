<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The kitchen refused the order: the dining room compensates by cancelling it.
 */
final readonly class CancelOrder implements Command
{
    public function __construct(
        public TableOrderId $tableOrderId,
        public string $reason,
    ) {}
}
