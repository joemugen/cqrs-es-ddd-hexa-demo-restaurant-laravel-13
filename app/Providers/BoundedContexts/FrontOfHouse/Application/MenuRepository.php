<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Menu;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItem;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;

interface MenuRepository
{
    /**
     * @param  list<MenuItemId>  $ids
     */
    public function containing(array $ids): Menu;

    public function findByName(DishName $name): MenuItem;

    public function save(MenuItem $item): void;
}
