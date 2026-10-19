<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * The only entry point of a consistency boundary. Rules live here, persistence does not.
 */
interface AggregateRoot
{
    public function id(): AggregateId;
}
