<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus;

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query\ListFloorPlanHandler;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query\ListMenuHandler;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query\ListToServeHandler;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\MiddlewareQueryBus;

final class FrontOfHouseQueryBus extends MiddlewareQueryBus
{
    protected function handlers(): array
    {
        return [
            ListFloorPlanHandler::class,
            ListMenuHandler::class,
            ListToServeHandler::class,
        ];
    }
}
