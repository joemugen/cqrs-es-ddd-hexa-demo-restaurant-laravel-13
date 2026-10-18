<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Console;

use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Console\ConsumeEvents;

final class ConsumeFrontOfHouseEvents extends ConsumeEvents
{
    protected function contextName(): string
    {
        return 'front-of-house';
    }
}
