<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Domain;

final readonly class StoredEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $position,
        public string $streamId,
        public int $version,
        public string $type,
        public array $payload,
        public string $occurredAt,
    ) {}
}
