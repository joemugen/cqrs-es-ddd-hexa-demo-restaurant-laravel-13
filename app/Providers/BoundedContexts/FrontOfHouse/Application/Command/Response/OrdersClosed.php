<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;

final readonly class OrdersClosed implements CommandResponse
{
    public function __construct(public TableId $tableId) {}

    public function jsonSerialize(): array
    {
        return [
            'tableId' => (string) $this->tableId,
            'accepted' => true,
        ];
    }
}
