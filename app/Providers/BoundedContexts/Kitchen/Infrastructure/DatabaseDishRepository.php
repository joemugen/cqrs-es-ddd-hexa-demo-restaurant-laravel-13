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
        $row = DB::table(Tables::DISHES)->where('id', (string) $id)->first();

        if ($row === null) {
            throw new KitchenException("Unknown dish {$id}.", 404);
        }

        return $this->dish($row);
    }

    public function called(array $names): array
    {
        return DB::table(Tables::DISHES)
            ->whereIn('name', array_map(fn (DishName $name): string => (string) $name, $names))
            ->get()
            ->map(fn (object $row): Dish => $this->dish($row))
            ->all();
    }

    public function recordEightySixedAt(DishId $id, int $position): void
    {
        DB::table(Tables::DISHES)->where('id', (string) $id)->update(['eighty_sixed_position' => $position]);
    }

    public function save(Dish $dish): void
    {
        DB::table(Tables::DISHES)->where('id', (string) $dish->id())->update([
            'in_stock' => $dish->isInStock(),
        ]);
    }

    private function dish(object $row): Dish
    {
        return Dish::restore(
            new DishId((string) $row->id),
            new DishName((string) $row->name),
            (string) $row->station,
            (int) $row->cooking_minutes,
            (bool) $row->in_stock,
            $row->eighty_sixed_position !== null ? (int) $row->eighty_sixed_position : null,
        );
    }
}
