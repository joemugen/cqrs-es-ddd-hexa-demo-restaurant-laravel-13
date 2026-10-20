<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Domain\ExpectedVersion;

final readonly class FireDishes implements Command
{
    public function __construct(
        public KitchenTicketId $ticketId,
        public string $course,
        public ?ExpectedVersion $expectedVersion = null,
    ) {}
}
