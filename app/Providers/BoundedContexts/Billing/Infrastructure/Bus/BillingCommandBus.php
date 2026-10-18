<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Bus;

use App\Providers\BoundedContexts\Billing\Application\Command\PayBillHandler;
use App\Providers\BoundedContexts\Billing\Application\Command\PrepareBillHandler;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\MiddlewareCommandBus;

final class BillingCommandBus extends MiddlewareCommandBus
{
    protected function handlers(): array
    {
        return [
            PayBillHandler::class,
            PrepareBillHandler::class,
        ];
    }
}
