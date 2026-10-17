<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\SharedKernel\Application\Command;

final readonly class MarkDishReady implements Command
{
    public function __construct(
        public string $ticketId,
        public string $lineId,
    ) {}
}
