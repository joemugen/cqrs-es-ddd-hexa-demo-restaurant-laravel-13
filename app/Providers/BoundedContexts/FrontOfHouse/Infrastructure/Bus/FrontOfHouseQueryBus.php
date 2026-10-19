<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus;

use App\Providers\Modules\SharedKernel\Infrastructure\Bus\MiddlewareQueryBus;

final class FrontOfHouseQueryBus extends MiddlewareQueryBus {}
