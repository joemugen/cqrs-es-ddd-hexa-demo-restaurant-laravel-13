<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\FreeTableHandler;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\PlaceTableOrderHandler;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\RequestBillHandler;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\SeatGuestsHandler;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ServeDishHandler;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\MiddlewareCommandBus;

final class FrontOfHouseCommandBus extends MiddlewareCommandBus
{
    protected function handlers(): array
    {
        return [
            FreeTableHandler::class,
            PlaceTableOrderHandler::class,
            RequestBillHandler::class,
            SeatGuestsHandler::class,
            ServeDishHandler::class,
        ];
    }
}
