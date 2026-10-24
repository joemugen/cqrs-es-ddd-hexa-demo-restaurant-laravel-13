<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Domain\NotFound;

/**
 * What was asked for in the dining room is unknown.
 */
final class FrontOfHouseNotFound extends DomainException implements NotFound {}
