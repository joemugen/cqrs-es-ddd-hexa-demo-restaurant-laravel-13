<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

use RuntimeException;

/**
 * A business refusal: a message for the caller and the HTTP status that fits it.
 */
abstract class DomainException extends RuntimeException
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
