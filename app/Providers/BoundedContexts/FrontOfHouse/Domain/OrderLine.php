<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

/**
 * A priced line of an order, copied from the menu at the time it was placed.
 */
final readonly class OrderLine
{
    /**
     * @param  list<string>  $allergens
     */
    public function __construct(
        public LineId $lineId,
        public MenuItemId $menuItemId,
        public DishName $name,
        public int $quantity,
        public string $course,
        public int $unitPriceCents,
        public string $kind,
        public array $allergens,
    ) {}

    /**
     * What the kitchen needs. The price stays on the line, for the till.
     *
     * @return array{lineId: string, name: string, quantity: int, course: string, allergens: list<string>}
     */
    public function forTheKitchen(): array
    {
        return [
            'lineId' => (string) $this->lineId,
            'name' => (string) $this->name,
            'quantity' => $this->quantity,
            'course' => $this->course,
            'allergens' => $this->allergens,
        ];
    }
}
