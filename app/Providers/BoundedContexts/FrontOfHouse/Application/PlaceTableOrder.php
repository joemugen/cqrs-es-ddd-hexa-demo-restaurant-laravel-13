<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application;

final readonly class PlaceTableOrder
{
    /**
     * @param  list<array{menuItemId: string, quantity: int, course: string}>  $lines
     */
    public function __construct(
        public string $tableOrderId,
        public string $tableId,
        public array $lines,
    ) {}
}
