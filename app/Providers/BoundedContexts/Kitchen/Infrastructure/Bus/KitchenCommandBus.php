<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus;

use App\Providers\BoundedContexts\Kitchen\Application\Command\DeclareDishEightySixedHandler;
use App\Providers\BoundedContexts\Kitchen\Application\Command\FireDishesHandler;
use App\Providers\BoundedContexts\Kitchen\Application\Command\MarkDishReadyHandler;
use App\Providers\BoundedContexts\Kitchen\Application\Command\OpenKitchenTicketHandler;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\MiddlewareCommandBus;

final class KitchenCommandBus extends MiddlewareCommandBus
{
    protected function handlers(): array
    {
        return [
            DeclareDishEightySixedHandler::class,
            FireDishesHandler::class,
            MarkDishReadyHandler::class,
            OpenKitchenTicketHandler::class,
        ];
    }
}
