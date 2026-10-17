<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\SharedKernel\Application\Command;

final readonly class SeatGuests implements Command
{
    public function __construct(
        public string $tableId,
        public int $guestCount,
    ) {}
}
