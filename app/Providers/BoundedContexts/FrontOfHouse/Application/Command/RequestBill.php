<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;

final readonly class RequestBill implements Command
{
    public function __construct(public TableId $tableId) {}
}
