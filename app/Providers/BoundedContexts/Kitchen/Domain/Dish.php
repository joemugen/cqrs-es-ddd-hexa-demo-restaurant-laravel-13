<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishEightySixed;
use App\Providers\Modules\SharedKernel\Domain\AggregateRoot;
use App\Providers\Modules\SharedKernel\Domain\RecordsEvents;

final class Dish implements AggregateRoot
{
    use RecordsEvents;

    private function __construct(
        private readonly DishId $id,
        private readonly DishName $name,
        private readonly string $station,
        private readonly int $cookingMinutes,
        private bool $inStock,
        private readonly ?int $eightySixedPosition = null,
    ) {}

    public static function restore(
        DishId $id,
        DishName $name,
        string $station,
        int $cookingMinutes,
        bool $inStock,
        ?int $eightySixedPosition = null,
    ): self {
        return new self($id, $name, $station, $cookingMinutes, $inStock, $eightySixedPosition);
    }

    public function eightySix(): void
    {
        if (! $this->inStock) {
            throw new KitchenException("{$this->name} is already 86.");
        }

        $this->inStock = false;
        $this->recordThat(new DishEightySixed($this->id, $this->name));
    }

    /**
     * A dish is judged where an order sits in the log: one declared 86 after the order was still available when it was
     * placed, however late a worker reads it. A dish out of stock with no recorded position predates the position and
     * counts as 86 for every order.
     */
    public function isAvailableAt(int $orderPosition): bool
    {
        return $this->inStock || ($this->eightySixedPosition !== null && $this->eightySixedPosition >= $orderPosition);
    }

    public function ticketLineFor(OrderedLine $line): TicketLine
    {
        return new TicketLine(
            lineId: $line->lineId,
            dishName: $this->name,
            station: $this->station,
            cookingMinutes: $this->cookingMinutes,
            quantity: $line->quantity,
            course: $line->course,
            allergens: array_values($line->allergens),
        );
    }

    public function isInStock(): bool
    {
        return $this->inStock;
    }

    public function id(): DishId
    {
        return $this->id;
    }

    public function name(): DishName
    {
        return $this->name;
    }
}
