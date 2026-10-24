<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure;

/**
 * The facts of other contexts that this one reacts to. Their names are a contract, copied here so no context imports another.
 */
final class ConsumedEvents
{
    public const FRONT_OF_HOUSE_TABLE_ORDER_PLACED = 'front_of_house.table_order_placed';

    public const FRONT_OF_HOUSE_TABLE_ORDER_REVISED = 'front_of_house.table_order_revised';

    public const FRONT_OF_HOUSE_TABLE_ORDER_CLOSED = 'front_of_house.table_order_closed';
}
