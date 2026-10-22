<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\Modules\SharedKernel\Application\IdempotentCommand;
use App\Providers\Modules\SharedKernel\Domain\IdempotencyKey;

final readonly class SeatGuests implements IdempotentCommand
{
    public function __construct(
        public TableId $tableId,
        public int $guestCount,
        public ?IdempotencyKey $idempotencyKey = null,
    ) {}

    public function idempotencyKey(): ?IdempotencyKey
    {
        return $this->idempotencyKey;
    }
}
