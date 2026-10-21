<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Publication;

/**
 * What a context reads of a contract of another one, in its own words and with its own types: the fuse on the reading
 * side. It keeps of the payload what the context needs, and a change of the payload that does not touch that leaves it
 * as it is. It is declared with `#[Consumes]` and found by convention.
 */
interface ConsumedMessage
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static;
}
