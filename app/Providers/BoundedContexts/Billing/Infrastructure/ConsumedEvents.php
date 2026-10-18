<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure;

/**
 * The facts of other contexts that this one reacts to. Their names are a contract, copied here so no context imports another.
 */
final class ConsumedEvents
{
    public const FRONT_OF_HOUSE_BILL_REQUESTED = 'front_of_house.bill_requested';

    public const FRONT_OF_HOUSE_DISH_SERVED = 'front_of_house.dish_served';
}
