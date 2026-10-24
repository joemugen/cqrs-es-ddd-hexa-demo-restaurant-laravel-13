<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Domain\RuleViolation;

/**
 * The payment closes the visit: nothing is served to guests who paid and left.
 */
final class VisitIsClosed extends DomainException implements RuleViolation
{
    public function __construct()
    {
        parent::__construct('The guests of this visit already paid and left: nothing is served to them.');
    }
}
