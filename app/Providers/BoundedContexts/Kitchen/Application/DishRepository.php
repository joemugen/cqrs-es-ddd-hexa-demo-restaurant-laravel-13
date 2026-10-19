<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

use App\Providers\BoundedContexts\Kitchen\Domain\Dish;
use App\Providers\BoundedContexts\Kitchen\Domain\DishId;

interface DishRepository
{
    public function get(DishId $id): Dish;

    public function save(Dish $dish): void;

    /**
     * Remember where in the event log the dish was declared 86, so orders can be judged against that moment.
     */
    public function recordEightySixedAt(DishId $id, int $position): void;
}
