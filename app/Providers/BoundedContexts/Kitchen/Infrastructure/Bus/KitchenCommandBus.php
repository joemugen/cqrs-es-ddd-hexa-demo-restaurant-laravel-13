<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus;

use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\RetryOnConflict;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\MiddlewareCommandBus;

final class KitchenCommandBus extends MiddlewareCommandBus
{
    protected function outsideTransaction(): array
    {
        return [RetryOnConflict::class];
    }
}
