<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\OrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;

interface OrderFulfilmentRepository
{
    public function exists(TableOrderId $id): bool;

    public function get(TableOrderId $id): OrderFulfilment;

    /**
     * @return list<OrderFulfilment>
     */
    public function awaitingRevisionAt(TableId $tableId): array;

    public function save(OrderFulfilment $fulfilment): void;
}
