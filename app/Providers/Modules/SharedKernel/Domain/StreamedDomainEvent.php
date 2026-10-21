<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * A fact of an aggregate rebuilt from its events. It is stored in the stream of the aggregate in a format the domain owns
 * and may change, under a name that is internal: no other context should know it.
 */
interface StreamedDomainEvent extends TypedDomainEvent
{
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
