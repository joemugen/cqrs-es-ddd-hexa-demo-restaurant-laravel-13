<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

/**
 * A line the kitchen cannot cook, and why.
 */
final readonly class UnavailableLine
{
    public function __construct(
        public LineId $lineId,
        public DishName $dishName,
        public string $reason,
    ) {}

    /**
     * @return array{lineId: string, name: string, reason: string}
     */
    public function toPayload(): array
    {
        return [
            'lineId' => (string) $this->lineId,
            'name' => (string) $this->dishName,
            'reason' => $this->reason,
        ];
    }
}
