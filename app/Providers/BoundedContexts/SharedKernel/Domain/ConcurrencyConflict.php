<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

/**
 * Somebody else changed the aggregate between the moment it was read and the moment it was saved.
 */
final class ConcurrencyConflict extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 409);
    }
}
