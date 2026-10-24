<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Domain\RuleViolation;

/**
 * The kitchen has not answered the last revision of the order yet: two revisions cannot be in flight.
 */
final class RevisionAlreadyWaiting extends DomainException implements RuleViolation
{
    public function __construct()
    {
        parent::__construct('A revision of this order is already waiting for the kitchen.');
    }
}
