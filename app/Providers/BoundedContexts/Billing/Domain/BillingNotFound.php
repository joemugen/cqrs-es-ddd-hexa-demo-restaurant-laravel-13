<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Domain\NotFound;

/**
 * What was asked for in the till is unknown.
 */
final class BillingNotFound extends DomainException implements NotFound {}
