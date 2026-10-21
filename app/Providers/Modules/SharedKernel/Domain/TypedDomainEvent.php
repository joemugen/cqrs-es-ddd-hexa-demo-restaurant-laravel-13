<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * A fact of the domain, with a shape. It is stored in the stream of its aggregate in a format the domain owns and may
 * change; what other contexts read is a contract that translates it, never the fact itself.
 *
 * `DomainEvent` is the envelope the event store keeps; this is the fact it carries.
 */
interface TypedDomainEvent
{
    /**
     * The name of the fact in the stream of its aggregate. It is internal: no other context should know it.
     */
    public function storedAs(): string;

    /**
     * @return array<string, mixed>
     */
    public function toStored(): array;

    /**
     * @param  array<string, mixed>  $stored
     */
    public static function fromStored(array $stored): static;
}
