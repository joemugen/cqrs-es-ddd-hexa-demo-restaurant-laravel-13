<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Infrastructure;

use App\Providers\BoundedContexts\EventStore\Domain\EventMessage;

final readonly class GenericMessage implements EventMessage
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private string $type,
        private array $payload,
        private string $occurredAt,
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
