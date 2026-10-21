<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\UnavailableLine;
use App\Providers\Modules\SharedKernel\Application\Command;

/**
 * The kitchen cannot cook some lines of the order: the waiter must ask the guests, and revise it.
 */
final readonly class MarkOrderAwaitingRevision implements Command
{
    /**
     * @param  list<UnavailableLine>  $lines
     * @param  int  $orderPosition  the position of the order, or revision, the kitchen answers
     */
    public function __construct(
        public TableOrderId $tableOrderId,
        public array $lines,
        public int $orderPosition,
    ) {}
}
