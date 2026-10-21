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
    ) {}

    public static function restore(
        DishId $id,
        DishName $name,
        string $station,
        int $cookingMinutes,
        bool $inStock,
    ): self {
        return new self($id, $name, $station, $cookingMinutes, $inStock);
    }

    public function eightySix(): void
    {
        if (! $this->inStock) {
            throw new KitchenException("{$this->name} is already 86.");
        }

        $this->inStock = false;
        $this->recordThat(new DishEightySixed($this->id, $this->name));
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
