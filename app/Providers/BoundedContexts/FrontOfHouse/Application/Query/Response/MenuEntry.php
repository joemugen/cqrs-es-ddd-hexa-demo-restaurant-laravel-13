<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use JsonSerializable;

final readonly class MenuEntry implements JsonSerializable
{
    /**
     * @param  list<string>  $allergens
     */
    public function __construct(
        public MenuItemId $menuItemId,
        public DishName $name,
        public string $description,
        public int $priceCents,
        public array $allergens,
        public bool $available,
        public string $kind,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'menuItemId' => (string) $this->menuItemId,
            'name' => (string) $this->name,
            'description' => $this->description,
            'priceCents' => $this->priceCents,
            'allergens' => $this->allergens,
            'available' => $this->available,
            'kind' => $this->kind,
        ];
    }
}
