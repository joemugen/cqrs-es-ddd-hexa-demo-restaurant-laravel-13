<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command\Response;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;

final readonly class SettleBillAccepted implements CommandResponse
{
    public function __construct(public BillId $billId) {}

    public function jsonSerialize(): array
    {
        return [
            'billId' => (string) $this->billId,
            'accepted' => true,
        ];
    }
}
