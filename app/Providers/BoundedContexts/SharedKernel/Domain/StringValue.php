<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

use JsonSerializable;
use Stringable;

/**
 * A value that is a string and nothing else: it gets a type so it cannot be swapped for another.
 */
abstract readonly class StringValue implements JsonSerializable, Stringable
{
    public function __construct(public string $value) {}

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
}
