<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

/**
 * The facts of other contexts that this one reacts to. Their names are a contract, copied here so no context imports another.
 */
final class ConsumedEvents
{
    public const FRONT_OF_HOUSE_TABLE_ORDER_PLACED = 'front_of_house.table_order_placed';

    public const FRONT_OF_HOUSE_TABLE_ORDER_REVISED = 'front_of_house.table_order_revised';

    public const FRONT_OF_HOUSE_DISH_SERVED = 'front_of_house.dish_served';

    public const KITCHEN_DISH_READY = 'kitchen.dish_ready';

    public const KITCHEN_DISH_EIGHTY_SIXED = 'kitchen.dish_eighty_sixed';

    public const KITCHEN_TICKET_OPENED = 'kitchen.ticket_opened';

    public const KITCHEN_DISHES_ADDED = 'kitchen.dishes_added';

    public const KITCHEN_LINES_UNAVAILABLE = 'kitchen.lines_unavailable';

    public const BILLING_BILL_PAID = 'billing.bill_paid';
}
