<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure;

use App\Providers\BoundedContexts\Kitchen\Application\DishRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\Dish;
use App\Providers\BoundedContexts\Kitchen\Domain\DishId;
use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;
use Illuminate\Support\Facades\DB;

final class DatabaseDishRepository implements DishRepository
{
    public function get(DishId $id): Dish
    {
        $row = DB::table('kitchen_dishes')->where('id', (string) $id)->first();

        if ($row === null) {
            throw new KitchenException("Unknown dish {$id}.", 404);
        }

        return Dish::restore(
            new DishId((string) $row->id),
            new DishName((string) $row->name),
            (string) $row->station,
            (int) $row->cooking_minutes,
            (bool) $row->in_stock,
        );
    }

    public function save(Dish $dish): void
    {
        DB::table('kitchen_dishes')->where('id', (string) $dish->id())->update([
            'in_stock' => $dish->isInStock(),
        ]);
    }
}
