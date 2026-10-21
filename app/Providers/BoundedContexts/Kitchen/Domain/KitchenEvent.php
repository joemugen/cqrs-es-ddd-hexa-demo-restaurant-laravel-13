<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

/**
 * The facts the kitchen records and publishes. Other contexts read these names; do not rename one lightly.
 */
final class KitchenEvent
{
    public const TICKET_OPENED = 'kitchen.ticket_opened';

    public const DISHES_ADDED = 'kitchen.dishes_added';

    public const DISH_FIRED = 'kitchen.dish_fired';

    public const DISH_READY = 'kitchen.dish_ready';

    public const TICKET_COMPLETED = 'kitchen.ticket_completed';

    public const DISH_EIGHTY_SIXED = 'kitchen.dish_eighty_sixed';

    public const LINES_UNAVAILABLE = 'kitchen.lines_unavailable';
}
