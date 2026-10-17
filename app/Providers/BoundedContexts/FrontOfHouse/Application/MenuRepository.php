<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Menu;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItem;

interface MenuRepository
{
    /**
     * @param  list<string>  $ids
     */
    public function containing(array $ids): Menu;

    public function findByName(string $name): MenuItem;

    public function save(MenuItem $item): void;
}
