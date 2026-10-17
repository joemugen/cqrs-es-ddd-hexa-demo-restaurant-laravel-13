<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\BoundedContexts\SharedKernel\Domain\AggregateRoot;

final class MenuItem implements AggregateRoot
{
    /**
     * @param  list<string>  $allergens
     */
    private function __construct(
        private readonly MenuItemId $id,
        private readonly DishName $name,
        private readonly string $description,
        private readonly int $priceCents,
        private readonly array $allergens,
        private bool $available,
        private readonly string $kind,
    ) {}

    /**
     * @param  list<string>  $allergens
     */
    public static function restore(
        MenuItemId $id,
        DishName $name,
        string $description,
        int $priceCents,
        array $allergens,
        bool $available,
        string $kind,
    ): self {
        return new self($id, $name, $description, $priceCents, $allergens, $available, $kind);
    }

    public function markUnavailable(): void
    {
        $this->available = false;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function id(): MenuItemId
    {
        return $this->id;
    }

    public function name(): DishName
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function priceCents(): int
    {
        return $this->priceCents;
    }

    /**
     * @return list<string>
     */
    public function allergens(): array
    {
        return $this->allergens;
    }

    public function kind(): string
    {
        return $this->kind;
    }
}
