<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Bus;

use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\MiddlewareQueryBus;

final class BillingQueryBus extends MiddlewareQueryBus {}
