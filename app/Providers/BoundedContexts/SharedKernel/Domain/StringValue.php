<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

use JsonSerializable;
use Stringable;

/**
 * A value that is a string and nothing else: it gets a type so it cannot be swapped for another,
 * and it refuses to exist when it breaks the rules of that type.
 */
abstract readonly class StringValue implements JsonSerializable, Stringable
{
    public function __construct(public string $value)
    {
        static::validate($value);
    }

    public function equals(self $other): bool
    {
        return $other::class === static::class && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    /**
     * @throws InvalidValue
     */
    protected static function validate(string $value): void {}

    /**
     * @throws InvalidValue
     */
    protected static function refuse(string $message): never
    {
        throw new InvalidValue($message);
    }

    /**
     * @throws InvalidValue
     */
    protected static function requireFilled(string $value, string $message): void
    {
        if (trim($value) === '') {
            self::refuse($message);
        }
    }

    /**
     * @throws InvalidValue
     */
    protected static function requireAtMost(string $value, int $max, string $subject): void
    {
        if (mb_strlen($value) > $max) {
            self::refuse("{$subject} cannot exceed {$max} characters.");
        }
    }

    /**
     * @throws InvalidValue
     */
    protected static function requireMatching(string $value, string $pattern, string $message): void
    {
        if (preg_match($pattern, $value) !== 1) {
            self::refuse($message);
        }
    }
}
