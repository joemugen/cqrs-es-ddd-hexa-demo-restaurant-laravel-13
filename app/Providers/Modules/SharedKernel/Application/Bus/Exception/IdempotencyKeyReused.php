<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Bus\Exception;

use App\Providers\Modules\SharedKernel\Application\Exception\Refusal;

final class IdempotencyKeyReused extends Refusal
{
    public function __construct()
    {
        parent::__construct('This Idempotency-Key was already used with another request.');
    }
}
