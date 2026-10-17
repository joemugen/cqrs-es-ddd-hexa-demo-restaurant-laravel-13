<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A fact that happened. The payload carries plain data only, so other contexts can read it.
 */
final readonly class DomainEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public EventType $type,
        public array $payload,
        public DateTimeImmutable $occurredAt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function now(string $type, array $payload): self
    {
        return new self(new EventType($type), $payload, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }
}
