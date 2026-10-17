<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

final readonly class EventType extends StringValue
{
    public function is(string $type): bool
    {
        return $this->value === $type;
    }
}
