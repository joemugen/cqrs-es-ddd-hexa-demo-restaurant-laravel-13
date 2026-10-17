<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Menu;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItem;
use Illuminate\Support\Facades\DB;

final class DatabaseMenuRepository implements MenuRepository
{
    public function containing(array $ids): Menu
    {
        $rows = DB::table('foh_menu_items')->whereIn('id', $ids)->get();
        $items = [];

        foreach ($rows as $row) {
            $item = $this->item($row);
            $items[$item->id()] = $item;
        }

        return new Menu($items);
    }

    public function findByName(string $name): MenuItem
    {
        $row = DB::table('foh_menu_items')->where('name', $name)->first();

        if ($row === null) {
            throw new FrontOfHouseException("Unknown menu item {$name}.", 404);
        }

        return $this->item($row);
    }

    public function save(MenuItem $item): void
    {
        DB::table('foh_menu_items')->where('id', $item->id())->update([
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
            (string) $row->id,
            (string) $row->name,
            (string) $row->description,
            (int) $row->price_cents,
            is_array($allergens) ? array_values($allergens) : [],
            (bool) $row->available,
            (string) $row->kind,
        );
    }
}
