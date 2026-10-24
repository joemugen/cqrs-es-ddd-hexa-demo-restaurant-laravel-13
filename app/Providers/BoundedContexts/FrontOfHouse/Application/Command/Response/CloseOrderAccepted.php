<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;

final readonly class CloseOrderAccepted implements CommandResponse
{
    public function __construct(public TableOrderId $tableOrderId) {}

    public function jsonSerialize(): array
    {
        return [
            'tableOrderId' => (string) $this->tableOrderId,
            'accepted' => true,
        ];
    }
}
