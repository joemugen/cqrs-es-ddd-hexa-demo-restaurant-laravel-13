<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

interface DishCatalog
{
    /**
     * Translate an ordered line into a dish the kitchen can cook. No price comes across.
     *
     * @param  list<array{lineId: string, name: string, quantity: int, course: string, allergens: list<string>}>  $ordered
     * @return list<array{lineId: string, dishName: string, station: string, cookingMinutes: int, quantity: int, course: string, allergens: list<string>}>
     */
    public function linesFor(array $ordered): array;
}
