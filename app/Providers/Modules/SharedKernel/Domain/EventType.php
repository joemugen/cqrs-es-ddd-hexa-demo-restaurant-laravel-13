<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

final readonly class EventType extends StringValue
{
    private const MAX_LENGTH = 100;

    public function is(string $type): bool
    {
        return $this->value === $type;
    }

    protected static function validate(string $value): void
    {
        self::requireFilled($value, 'An event type cannot be blank.');
        self::requireAtMost($value, self::MAX_LENGTH, 'An event type');
        self::requireMatching(
            $value,
            '/^[a-z0-9_.]+$/D',
            'An event type may only contain lowercase letters, digits, dots and underscores.',
        );
    }
}
