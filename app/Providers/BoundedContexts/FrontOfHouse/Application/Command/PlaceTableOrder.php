<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\Command;

final readonly class PlaceTableOrder implements Command
{
    /**
     * @param  list<RequestedLine>  $lines
     */
    public function __construct(
        public TableOrderId $tableOrderId,
        public TableId $tableId,
        public array $lines,
    ) {}
}
