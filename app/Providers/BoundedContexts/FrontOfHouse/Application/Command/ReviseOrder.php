<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\IdempotentCommand;
use App\Providers\Modules\SharedKernel\Domain\IdempotencyKey;

/**
 * The guests chose replacements for the dishes the kitchen could not cook. The unavailable lines are abandoned.
 */
final readonly class ReviseOrder implements IdempotentCommand
{
    /**
     * @param  list<RequestedLine>  $lines
     */
    public function __construct(
        public TableOrderId $tableOrderId,
        public array $lines,
        public ?IdempotencyKey $idempotencyKey = null,
    ) {}

    public function idempotencyKey(): ?IdempotencyKey
    {
        return $this->idempotencyKey;
    }
}
