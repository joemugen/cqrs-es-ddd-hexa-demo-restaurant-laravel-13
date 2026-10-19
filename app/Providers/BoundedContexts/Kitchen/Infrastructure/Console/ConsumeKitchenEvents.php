<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Console;

use App\Providers\Modules\SharedKernel\Infrastructure\Console\ConsumeEvents;

final class ConsumeKitchenEvents extends ConsumeEvents
{
    protected function contextName(): string
    {
        return 'kitchen';
    }
}
