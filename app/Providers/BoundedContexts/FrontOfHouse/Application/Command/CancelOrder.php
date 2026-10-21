<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The guests give up the dishes that are still unavailable: the order is cancelled, and what is already cooking goes on.
 */
final readonly class CancelOrder implements Command
{
    public function __construct(
        public TableOrderId $tableOrderId,
        public string $reason,
    ) {}
}
