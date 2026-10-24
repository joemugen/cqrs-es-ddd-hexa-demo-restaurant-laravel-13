<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseNotFound;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Menu;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItem;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use Illuminate\Support\Facades\DB;

final class DatabaseMenuRepository implements MenuRepository
{
    public function containing(array $ids): Menu
    {
        $rows = DB::table(Tables::MENU_ITEMS)
            ->whereIn('id', array_map(fn (MenuItemId $id): string => (string) $id, $ids))
            ->get();
        $items = [];

        foreach ($rows as $row) {
            $item = $this->item($row);
            $items[(string) $item->id()] = $item;
        }

        return new Menu($items);
    }

    public function findByName(DishName $name): MenuItem
    {
        $row = DB::table(Tables::MENU_ITEMS)->where('name', (string) $name)->first();

        if ($row === null) {
            throw new FrontOfHouseNotFound("Unknown menu item {$name}.");
        }

        return $this->item($row);
    }

    public function save(MenuItem $item): void
    {
        DB::table(Tables::MENU_ITEMS)->where('id', (string) $item->id())->update([
            'available' => $item->isAvailable(),
        ]);
    }

    private function item(object $row): MenuItem
    {
        $allergens = $row->allergens;
        if (is_string($allergens)) {
            $allergens = json_decode($allergens, true, 512, JSON_THROW_ON_ERROR);
        }

        return MenuItem::restore(
            new MenuItemId((string) $row->id),
            new DishName((string) $row->name),
            (string) $row->description,
            (int) $row->price_cents,
            is_array($allergens) ? array_values($allergens) : [],
            (bool) $row->available,
            (string) $row->kind,
        );
    }
}
