<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * The name a caller gives to one attempt at an operation, so that retrying it is safe.
 */
final readonly class IdempotencyKey extends StringValue
{
    public const MAX_LENGTH = 255;

    protected static function validate(string $value): void
    {
        self::requireFilled($value, 'An idempotency key cannot be blank.');
        self::requireAtMost($value, self::MAX_LENGTH, 'An idempotency key');
    }
}
