<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

use App\Providers\Modules\SharedKernel\Domain\AggregateId;
use App\Providers\Modules\SharedKernel\Domain\Identifier;

final readonly class CoverRef extends Identifier implements AggregateId {}
