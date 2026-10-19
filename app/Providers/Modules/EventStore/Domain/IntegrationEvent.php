<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Domain;

use App\Providers\BoundedContexts\SharedKernel\Domain\EventType;

/**
 * A fact one context tells the others. It carries plain data: listeners do not share a class with the publisher.
 */
final readonly class IntegrationEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public EventType $type,
        public array $payload,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function named(string $type, array $payload): self
    {
        return new self(new EventType($type), $payload);
    }
}
