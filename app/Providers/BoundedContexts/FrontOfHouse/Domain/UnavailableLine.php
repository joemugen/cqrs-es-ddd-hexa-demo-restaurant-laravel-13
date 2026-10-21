<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

/**
 * A line of an order the kitchen cannot cook, and why. The guests are told, and the line is abandoned.
 */
final readonly class UnavailableLine
{
    public function __construct(
        public LineId $lineId,
        public DishName $name,
        public string $reason,
    ) {}

    /**
     * @param  array{lineId: string, name: string, reason: string}  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            new LineId((string) $payload['lineId']),
            new DishName((string) $payload['name']),
            (string) $payload['reason'],
        );
    }

    /**
     * @return array{lineId: string, name: string, reason: string}
     */
    public function toPayload(): array
    {
        return [
            'lineId' => (string) $this->lineId,
            'name' => (string) $this->name,
            'reason' => $this->reason,
        ];
    }
}
