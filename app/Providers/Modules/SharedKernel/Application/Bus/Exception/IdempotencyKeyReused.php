<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Bus\Exception;

use App\Providers\Modules\SharedKernel\Domain\DomainException;

final class IdempotencyKeyReused extends DomainException
{
    public function __construct()
    {
        parent::__construct('This Idempotency-Key was already used with another request.', 422);
    }
}
