<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Domain;

use App\Providers\Modules\SharedKernel\Domain\EventType;
use App\Providers\Modules\SharedKernel\Domain\StreamId;

final readonly class StoredEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $position,
        public StreamId $streamId,
        public int $version,
        public EventType $type,
        public array $payload,
        public string $occurredAt,
        public ?string $correlationId = null,
        public ?string $causationId = null,
        public ?int $contractVersion = null,
    ) {}
}
