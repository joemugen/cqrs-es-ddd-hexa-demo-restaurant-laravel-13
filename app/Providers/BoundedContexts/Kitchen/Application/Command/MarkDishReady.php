<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\Concurrency\ExpectedVersion;

final readonly class MarkDishReady implements Command
{
    public function __construct(
        public KitchenTicketId $ticketId,
        public LineId $lineId,
        public ?ExpectedVersion $expectedVersion = null,
    ) {}
}
