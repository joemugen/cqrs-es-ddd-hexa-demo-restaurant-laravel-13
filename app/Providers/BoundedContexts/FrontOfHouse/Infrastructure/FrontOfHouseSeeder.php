<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

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
            DB::table('foh_tables')->insert([
                'id' => $table['id'],
                'label' => $table['label'],
                'guest_count' => 0,
            ]);

            DB::table('foh_floor_plan')->insert([
                'table_id' => $table['id'],
                'label' => $table['label'],
                'guest_count' => 0,
                'status' => 'free',
            ]);
        }

        $menu = [
            ['id' => 'creme-brulee', 'name' => 'Creme brulee', 'description' => 'Vanilla custard, burnt sugar.', 'price_cents' => 1100, 'allergens' => ['dairy', 'eggs'], 'kind' => 'food'],
            ['id' => 'green-salad', 'name' => 'Green salad', 'description' => 'Leaves, herbs, lemon.', 'price_cents' => 1100, 'allergens' => [], 'kind' => 'food'],
            ['id' => 'house-red', 'name' => 'House red', 'description' => 'A glass of the house red.', 'price_cents' => 600, 'allergens' => ['sulfites'], 'kind' => 'alcohol'],
            ['id' => 'steak-frites', 'name' => 'Steak frites', 'description' => 'Grilled beef, fries.', 'price_cents' => 2200, 'allergens' => ['gluten'], 'kind' => 'food'],
        ];

        foreach ($menu as $item) {
            DB::table('foh_menu_items')->insert([
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
