<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Bus;

use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\MiddlewareCommandBus;

final class BillingCommandBus extends MiddlewareCommandBus {}
