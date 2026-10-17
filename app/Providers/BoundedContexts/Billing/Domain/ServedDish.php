<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

final readonly class ServedDish
{
    public function __construct(
        public SourceKey $sourceKey,
        public string $description,
        public int $quantity,
        public int $unitPriceCents,
        public string $kind,
    ) {}

    public function grossCents(): int
    {
        return $this->unitPriceCents * $this->quantity;
    }

    public function vatCents(): int
    {
        return Vat::cents($this->grossCents(), $this->kind);
    }
}
