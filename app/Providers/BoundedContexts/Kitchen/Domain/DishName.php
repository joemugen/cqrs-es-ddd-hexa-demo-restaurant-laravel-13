<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

use App\Providers\BoundedContexts\SharedKernel\Domain\StringValue;

final readonly class DishName extends StringValue
{
    private const MAX_LENGTH = 80;

    protected static function validate(string $value): void
    {
        self::requireFilled($value, 'A dish name cannot be blank.');
        self::requireAtMost($value, self::MAX_LENGTH, 'A dish name cannot exceed :max characters.');
        self::requireMatching($value, '/^[^\x00-\x1F\x7F]+$/D', 'A dish name cannot contain control characters.');
    }
}
