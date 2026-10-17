<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

final readonly class Menu
{
    /**
     * @param  array<string, MenuItem>  $items
     */
    public function __construct(private array $items) {}

    public function get(MenuItemId $id): MenuItem
    {
        return $this->items[(string) $id] ?? throw new FrontOfHouseException("Unknown menu item {$id}.", 404);
    }
}
