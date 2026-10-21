<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A fact as the event store keeps it: a type, a payload of plain data, and when it happened. A contract carries the
 * version it is published as. An aggregate that records a typed fact keeps it too, so it can be translated.
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
        public ?int $contractVersion = null,
        public ?TypedDomainEvent $fact = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function now(string $type, array $payload, ?int $contractVersion = null): self
    {
        return new self(new EventType($type), $payload, new DateTimeImmutable('now', new DateTimeZone('UTC')), $contractVersion);
    }

    public static function recording(TypedDomainEvent $fact): self
    {
        return new self(
            new EventType($fact->storedAs()),
            $fact->toStored(),
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
            fact: $fact,
        );
    }
}
