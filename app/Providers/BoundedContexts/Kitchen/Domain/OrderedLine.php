<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

/**
 * A line the dining room ordered, before the kitchen has translated it into a dish.
 */
final readonly class OrderedLine
{
    /**
     * @param  list<string>  $allergens
     */
    public function __construct(
        public LineId $lineId,
        public DishName $name,
        public int $quantity,
        public string $course,
        public array $allergens,
    ) {}
}
