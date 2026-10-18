<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

/**
 * What a menu item is: the till taxes food and alcohol differently.
 */
final class DishKind
{
    public const FOOD = 'food';

    public const ALCOHOL = 'alcohol';
}
