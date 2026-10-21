<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;

interface TableOrderRepository
{
    public function exists(TableOrderId $id): bool;

    public function get(TableOrderId $id): TableOrder;

    public function save(TableOrder $order): void;
}
