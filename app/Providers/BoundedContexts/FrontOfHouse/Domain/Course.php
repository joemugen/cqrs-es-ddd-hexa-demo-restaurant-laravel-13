<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

/**
 * The courses a guest can order.
 */
final class Course
{
    public const STARTER = 'starter';

    public const MAIN = 'main';

    public const DESSERT = 'dessert';

    public const DRINK = 'drink';

    /**
     * @var list<string>
     */
    public const ALL = [self::STARTER, self::MAIN, self::DESSERT, self::DRINK];
}
