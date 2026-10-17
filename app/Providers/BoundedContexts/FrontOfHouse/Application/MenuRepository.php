<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Menu;

interface MenuRepository
{
    /**
     * @param  list<string>  $ids
     */
    public function containing(array $ids): Menu;
}
