<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus\Idempotency;

use App\Providers\Modules\SharedKernel\Application\CommandResponse;

/**
 * The response a command got the first time, read back from the store: it serializes to the same JSON.
 */
final readonly class ReplayedResponse implements CommandResponse
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private array $payload) {}

    public function jsonSerialize(): array
    {
        return $this->payload;
    }
}
