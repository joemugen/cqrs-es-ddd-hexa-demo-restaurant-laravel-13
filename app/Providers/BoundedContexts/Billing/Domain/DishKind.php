<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

/**
 * What a served dish is: food eaten in is taxed at 10 percent, alcohol at 20.
 */
final class DishKind
{
    public const FOOD = 'food';

    public const ALCOHOL = 'alcohol';
}
