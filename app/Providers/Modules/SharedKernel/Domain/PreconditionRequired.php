<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * The command is only valid for a state of the resource that the client must name, and it named none.
 */
final class PreconditionRequired extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 428);
    }
}
