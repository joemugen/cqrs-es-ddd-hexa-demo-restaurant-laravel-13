<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishKind;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class FrontOfHouseSeeder extends Seeder
{
    public function run(): void
    {
        $tables = [
            ['id' => 'table-4', 'label' => 'Window 4'],
            ['id' => 'table-7', 'label' => 'Terrace 7'],
        ];

        foreach ($tables as $table) {
            DB::table(Tables::TABLES)->insert([
                'id' => $table['id'],
                'label' => $table['label'],
                'guest_count' => 0,
            ]);

            DB::table(Tables::FLOOR_PLAN)->insert([
                'table_id' => $table['id'],
                'label' => $table['label'],
                'guest_count' => 0,
                'status' => TableStatus::FREE,
            ]);
        }

        $menu = [
            ['id' => 'creme-brulee', 'name' => 'Creme brulee', 'description' => 'Vanilla custard, burnt sugar.', 'price_cents' => 1100, 'allergens' => ['dairy', 'eggs'], 'kind' => DishKind::FOOD],
            ['id' => 'green-salad', 'name' => 'Green salad', 'description' => 'Leaves, herbs, lemon.', 'price_cents' => 1100, 'allergens' => [], 'kind' => DishKind::FOOD],
            ['id' => 'house-red', 'name' => 'House red', 'description' => 'A glass of the house red.', 'price_cents' => 600, 'allergens' => ['sulfites'], 'kind' => DishKind::ALCOHOL],
            ['id' => 'steak-frites', 'name' => 'Steak frites', 'description' => 'Grilled beef, fries.', 'price_cents' => 2200, 'allergens' => ['gluten'], 'kind' => DishKind::FOOD],
        ];

        foreach ($menu as $item) {
            DB::table(Tables::MENU_ITEMS)->insert([
                'id' => $item['id'],
                'name' => $item['name'],
                'description' => $item['description'],
                'price_cents' => $item['price_cents'],
                'allergens' => json_encode($item['allergens'], JSON_THROW_ON_ERROR),
                'available' => true,
                'kind' => $item['kind'],
            ]);
        }
    }
}
