<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Domain\NotFound;

/**
 * What was asked for in the kitchen is unknown.
 */
final class KitchenNotFound extends DomainException implements NotFound {}
