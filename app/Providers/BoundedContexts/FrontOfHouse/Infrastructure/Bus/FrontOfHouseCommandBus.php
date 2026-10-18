<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus;

use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\MiddlewareCommandBus;

final class FrontOfHouseCommandBus extends MiddlewareCommandBus {}
