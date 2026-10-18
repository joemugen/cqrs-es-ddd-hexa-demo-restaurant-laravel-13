<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Bus;

use App\Providers\BoundedContexts\Billing\Infrastructure\Query\GetBillHandler;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\MiddlewareQueryBus;

final class BillingQueryBus extends MiddlewareQueryBus
{
    protected function handlers(): array
    {
        return [
            GetBillHandler::class,
        ];
    }
}
