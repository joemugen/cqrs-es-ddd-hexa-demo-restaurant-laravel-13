<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command\Response;

use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandResponse;

final readonly class BillPrepared implements CommandResponse
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
