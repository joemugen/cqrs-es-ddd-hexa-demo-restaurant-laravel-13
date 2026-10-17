<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Domain;

interface EventMessage
{
    public function type(): string;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;

    public function occurredAt(): string;
}
