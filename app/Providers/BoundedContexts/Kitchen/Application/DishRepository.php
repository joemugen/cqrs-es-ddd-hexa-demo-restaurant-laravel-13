<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

use App\Providers\BoundedContexts\Kitchen\Domain\Dish;

interface DishRepository
{
    public function get(string $id): Dish;

    public function save(Dish $dish): void;
}
