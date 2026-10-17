<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

use RuntimeException;

final class BillingException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
