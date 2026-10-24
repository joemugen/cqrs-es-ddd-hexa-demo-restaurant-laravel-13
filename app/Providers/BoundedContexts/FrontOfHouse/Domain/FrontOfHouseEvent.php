<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

/**
 * The facts the dining room publishes. Other contexts read these names; do not rename one lightly.
 */
final class FrontOfHouseEvent
{
    public const TABLE_ORDER_PLACED = 'front_of_house.table_order_placed';

    public const BILL_REQUESTED = 'front_of_house.bill_requested';

    public const DISH_SERVED = 'front_of_house.dish_served';

    public const TABLE_ORDER_REVISED = 'front_of_house.table_order_revised';

    public const TABLE_ORDER_CANCELLED = 'front_of_house.table_order_cancelled';

    public const TABLE_ORDER_CLOSED = 'front_of_house.table_order_closed';
}
