<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

/**
 * The names under which the kitchen publishes its contracts. Other contexts read these names; do not rename one lightly.
 */
final class KitchenEvent
{
    public const TICKET_OPENED = 'kitchen.ticket_opened';

    public const DISHES_ADDED = 'kitchen.dishes_added';

    public const DISH_READY = 'kitchen.dish_ready';

    public const DISH_EIGHTY_SIXED = 'kitchen.dish_eighty_sixed';

    public const LINES_UNAVAILABLE = 'kitchen.lines_unavailable';
}
