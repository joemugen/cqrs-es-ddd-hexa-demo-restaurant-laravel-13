<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePass;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;

interface PassRepository
{
    public function exists(DishAtThePassId $id): bool;

    public function find(DishAtThePassId $id): DishAtThePass;

    public function save(DishAtThePass $dish): void;
}
