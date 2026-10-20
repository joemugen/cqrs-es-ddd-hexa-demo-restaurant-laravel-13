<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Query;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\Query;

final readonly class ShowOrderFulfilment implements Query
{
    public function __construct(public TableOrderId $tableOrderId) {}
}
