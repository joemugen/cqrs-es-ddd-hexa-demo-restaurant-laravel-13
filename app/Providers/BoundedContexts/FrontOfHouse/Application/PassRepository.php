<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePass;

interface PassRepository
{
    public function find(string $id): DishAtThePass;

    public function save(DishAtThePass $dish): void;
}
