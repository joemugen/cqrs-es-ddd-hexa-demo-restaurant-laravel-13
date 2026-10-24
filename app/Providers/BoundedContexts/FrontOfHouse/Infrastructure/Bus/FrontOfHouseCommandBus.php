<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus;

use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\RetryOnConflict;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\MiddlewareCommandBus;

final class FrontOfHouseCommandBus extends MiddlewareCommandBus
{
    protected function outsideTransaction(): array
    {
        return [RetryOnConflict::class];
    }
}
