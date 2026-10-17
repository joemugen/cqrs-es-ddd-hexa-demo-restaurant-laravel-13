<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;

interface TableOrderRepository
{
    public function save(TableOrder $order): void;
}
