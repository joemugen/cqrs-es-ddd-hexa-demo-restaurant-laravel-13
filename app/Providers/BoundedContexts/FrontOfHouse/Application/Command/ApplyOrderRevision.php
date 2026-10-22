<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The guests revised the order: the saga that follows it asks the kitchen again.
 */
final readonly class ApplyOrderRevision implements Command
{
    public function __construct(
        public TableOrderId $tableOrderId,
        public int $revision,
        public int $revisionPosition,
    ) {}
}
