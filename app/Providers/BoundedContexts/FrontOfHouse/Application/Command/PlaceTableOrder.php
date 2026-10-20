<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\IdempotentCommand;
use App\Providers\Modules\SharedKernel\Domain\IdempotencyKey;

final readonly class PlaceTableOrder implements IdempotentCommand
{
    /**
     * @param  list<RequestedLine>  $lines
     */
    public function __construct(
        public TableOrderId $tableOrderId,
        public TableId $tableId,
        public array $lines,
        public ?IdempotencyKey $idempotencyKey = null,
    ) {}

    public function idempotencyKey(): ?IdempotencyKey
    {
        return $this->idempotencyKey;
    }
}
