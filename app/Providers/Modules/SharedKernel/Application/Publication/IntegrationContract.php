<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Publication;

/**
 * What a context promises to the others: the only public shape of a fact. A contract is declared with
 * `#[PublishedAs]` and `#[Translates]`, is built by a static `from()` taking the fact it translates, and is read back by
 * a consumer from its payload.
 */
interface IntegrationContract
{
    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static;
}
