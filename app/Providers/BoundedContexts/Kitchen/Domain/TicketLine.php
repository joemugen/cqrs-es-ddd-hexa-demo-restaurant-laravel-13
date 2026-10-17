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
