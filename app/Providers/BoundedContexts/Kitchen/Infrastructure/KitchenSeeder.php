<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class KitchenSeeder extends Seeder
{
    public function run(): void
    {
        $dishes = [
            ['id' => 'dish-brulee', 'name' => 'Creme brulee', 'station' => 'pastry', 'cooking_minutes' => 8],
            ['id' => 'dish-red', 'name' => 'House red', 'station' => 'bar', 'cooking_minutes' => 1],
            ['id' => 'dish-salad', 'name' => 'Green salad', 'station' => 'garnish', 'cooking_minutes' => 4],
            ['id' => 'dish-steak', 'name' => 'Steak frites', 'station' => 'grill', 'cooking_minutes' => 12],
        ];

        foreach ($dishes as $dish) {
            DB::table('kitchen_dishes')->insert([
                'id' => $dish['id'],
                'name' => $dish['name'],
                'station' => $dish['station'],
                'cooking_minutes' => $dish['cooking_minutes'],
                'in_stock' => true,
            ]);
        }
    }
}
