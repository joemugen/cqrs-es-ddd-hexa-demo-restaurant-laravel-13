<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\SharedKernel\Application\Command;

final readonly class PlaceTableOrder implements Command
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
