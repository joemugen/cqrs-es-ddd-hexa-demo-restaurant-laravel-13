<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Console;

use App\Providers\Modules\SharedKernel\Infrastructure\Console\ConsumeEvents;

final class ConsumeBillingEvents extends ConsumeEvents
{
    protected function contextName(): string
    {
        return 'billing';
    }
}
