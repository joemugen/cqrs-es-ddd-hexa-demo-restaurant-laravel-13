<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Domain;

use App\Providers\Modules\SharedKernel\Domain\EventType;

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
        public ?int $version = null,
    ) {}
}
