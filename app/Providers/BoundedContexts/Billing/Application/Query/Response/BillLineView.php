<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Query\Response;

use JsonSerializable;

final readonly class BillLineView implements JsonSerializable
{
    public function __construct(
        public string $description,
        public int $quantity,
        public int $unitPriceCents,
        public string $kind,
        public int $vatCents,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitPriceCents' => $this->unitPriceCents,
            'kind' => $this->kind,
            'vatCents' => $this->vatCents,
        ];
    }
}
