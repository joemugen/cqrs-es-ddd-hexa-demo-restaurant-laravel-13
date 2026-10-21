<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

/**
 * One dish on a ticket, as the kitchen cooks it. No price comes across.
 */
final readonly class TicketLine
{
    /**
     * @param  list<string>  $allergens
     */
    public function __construct(
        public LineId $lineId,
        public DishName $dishName,
        public string $station,
        public int $cookingMinutes,
        public int $quantity,
        public string $course,
        public array $allergens,
    ) {}

    /**
     * @param  array{lineId: string, dishName: string, station: string, cookingMinutes: int, quantity: int, course: string, allergens: list<string>}  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            new LineId((string) $payload['lineId']),
            new DishName((string) $payload['dishName']),
            (string) $payload['station'],
            (int) $payload['cookingMinutes'],
            (int) $payload['quantity'],
            (string) $payload['course'],
            array_values($payload['allergens']),
        );
    }

    /**
     * @return array{lineId: string, dishName: string, station: string, cookingMinutes: int, quantity: int, course: string, allergens: list<string>}
     */
    public function toPayload(): array
    {
        return [
            'lineId' => (string) $this->lineId,
            'dishName' => (string) $this->dishName,
            'station' => $this->station,
            'cookingMinutes' => $this->cookingMinutes,
            'quantity' => $this->quantity,
            'course' => $this->course,
            'allergens' => $this->allergens,
        ];
    }
}
