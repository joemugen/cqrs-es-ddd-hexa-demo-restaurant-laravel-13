<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain\Event;

use App\Providers\BoundedContexts\Kitchen\Domain\DishId;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * The kitchen ran out of a dish.
 */
final readonly class DishEightySixed implements TypedDomainEvent
{
    public function __construct(
        public DishId $dishId,
        public DishName $dishName,
    ) {}
}
