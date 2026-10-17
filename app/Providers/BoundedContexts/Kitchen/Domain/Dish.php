<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

final class Dish
{
    private function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $station,
        private readonly int $cookingMinutes,
        private bool $inStock,
    ) {}

    public static function restore(
        string $id,
        string $name,
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
    }

    public function isInStock(): bool
    {
        return $this->inStock;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}
