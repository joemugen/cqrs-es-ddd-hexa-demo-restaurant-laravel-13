<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command;

use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * A dish left the pass for a cover: it goes on the bill that is open, or waits for the one that will be.
 */
final readonly class RecordServedDish implements Command
{
    public function __construct(
        public CoverRef $coverRef,
        public ServedDish $dish,
        public int $position,
    ) {}
}
