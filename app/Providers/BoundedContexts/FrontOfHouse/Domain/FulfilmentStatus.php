<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

/**
 * Where an order stands between the dining room and the kitchen.
 */
final class FulfilmentStatus
{
    public const AWAITING_KITCHEN = 'awaiting-kitchen';

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';
}
