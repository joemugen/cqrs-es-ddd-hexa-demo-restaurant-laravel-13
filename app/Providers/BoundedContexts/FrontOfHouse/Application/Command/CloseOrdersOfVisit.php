<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The guests of a visit paid and left: an order of theirs that still awaits a revision will never get one.
 */
final readonly class CloseOrdersOfVisit implements Command
{
    public function __construct(public VisitId $visitId) {}
}
