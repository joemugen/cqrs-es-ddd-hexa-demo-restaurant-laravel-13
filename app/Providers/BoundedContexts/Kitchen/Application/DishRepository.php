<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

use App\Providers\BoundedContexts\Kitchen\Domain\Dish;
use App\Providers\BoundedContexts\Kitchen\Domain\DishId;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;

interface DishRepository
{
    public function get(DishId $id): Dish;

    /**
     * The dishes of the kitchen that have these names.
     *
     * @param  list<DishName>  $names
     * @return list<Dish>
     */
    public function called(array $names): array;

    public function save(Dish $dish): void;

    /**
     * Remember where in the event log the dish was declared 86, so orders can be judged against that moment.
     */
    public function recordEightySixedAt(DishId $id, int $position): void;
}
