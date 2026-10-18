<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

final readonly class StreamId extends StringValue
{
    private const MAX_LENGTH = 150;

    protected static function validate(string $value): void
    {
        self::requireFilled($value, 'A stream id cannot be blank.');
        self::requireAtMost($value, self::MAX_LENGTH, 'A stream id');
    }
}
