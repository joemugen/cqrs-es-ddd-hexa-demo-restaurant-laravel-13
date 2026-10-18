<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

/**
 * A reference to something by its identity: a ULID or a slug, short, and safe in a URL.
 */
abstract readonly class Identifier extends StringValue
{
    private const MAX_LENGTH = 64;

    protected static function validate(string $value): void
    {
        self::requireFilled($value, 'An identifier cannot be blank.');
        self::requireAtMost($value, self::MAX_LENGTH, 'An identifier cannot exceed :max characters.');
        self::requireMatching(
            $value,
            '/^[A-Za-z0-9_-]+$/D',
            'An identifier may only contain letters, digits, dashes and underscores.',
        );
    }
}
