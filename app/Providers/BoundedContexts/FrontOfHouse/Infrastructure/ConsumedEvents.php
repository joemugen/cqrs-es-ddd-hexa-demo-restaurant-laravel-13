<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

/**
 * The facts of other contexts that this one reacts to. Their names are a contract, copied here so no context imports another.
 */
final class ConsumedEvents
{
    public const KITCHEN_DISH_READY = 'kitchen.dish_ready';

    public const KITCHEN_DISH_EIGHTY_SIXED = 'kitchen.dish_eighty_sixed';

    public const KITCHEN_TICKET_OPENED = 'kitchen.ticket_opened';

    public const KITCHEN_ORDER_REFUSED = 'kitchen.order_refused';

    public const BILLING_BILL_PAID = 'billing.bill_paid';
}
