<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure;

use App\Providers\BoundedContexts\Kitchen\Application\DishCatalog;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;
use Illuminate\Support\Facades\DB;

final class DatabaseDishCatalog implements DishCatalog
{
    public function linesFor(array $ordered): array
    {
        $lines = [];

        foreach ($ordered as $line) {
            $dish = DB::table('kitchen_dishes')->where('name', $line['name'])->first();

            if ($dish === null) {
                throw new KitchenException("The kitchen has no dish called {$line['name']}.");
            }

            if (! $dish->in_stock) {
                throw new KitchenException("{$dish->name} is 86.");
            }

            $lines[] = [
                'lineId' => $line['lineId'],
                'dishName' => (string) $dish->name,
                'station' => (string) $dish->station,
                'cookingMinutes' => (int) $dish->cooking_minutes,
                'quantity' => (int) $line['quantity'],
                'course' => $line['course'],
                'allergens' => array_values($line['allergens']),
            ];
        }

        return $lines;
    }
}
