<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The guests of the order paid and left: the order will never get the revision it may still await.
 */
final readonly class CloseOrder implements Command
{
    public function __construct(public TableOrderId $tableOrderId) {}
}
