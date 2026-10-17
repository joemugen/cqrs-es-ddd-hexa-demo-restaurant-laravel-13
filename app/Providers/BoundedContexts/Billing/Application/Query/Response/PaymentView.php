<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Query\Response;

use JsonSerializable;

final readonly class PaymentView implements JsonSerializable
{
    public function __construct(
        public int $amountCents,
        public int $tipCents,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'amountCents' => $this->amountCents,
            'tipCents' => $this->tipCents,
        ];
    }
}
