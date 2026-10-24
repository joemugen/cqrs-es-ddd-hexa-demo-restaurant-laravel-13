<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Domain\RuleViolation;

/**
 * The waiter decided on an order that is no longer open to revision: the guests paid and left.
 */
final class ObsoleteRevision extends DomainException implements RuleViolation
{
    public function __construct()
    {
        parent::__construct('The revision of the order is obsolete: the guests already paid and left.');
    }
}
