<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

use App\Providers\BoundedContexts\SharedKernel\Domain\StringValue;

final readonly class TableLabel extends StringValue
{
    private const MAX_LENGTH = 40;

    protected static function validate(string $value): void
    {
        self::requireFilled($value, 'A table label cannot be blank.');
        self::requireAtMost($value, self::MAX_LENGTH, 'A table label cannot exceed :max characters.');
        self::requireMatching($value, '/^[^\x00-\x1F\x7F]+$/D', 'A table label cannot contain control characters.');
    }
}
