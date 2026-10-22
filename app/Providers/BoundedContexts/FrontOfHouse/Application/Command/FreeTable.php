<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The guests of a visit paid and left: the table that hosts them is free again.
 */
final readonly class FreeTable implements Command
{
    public function __construct(public VisitId $visitId) {}
}
