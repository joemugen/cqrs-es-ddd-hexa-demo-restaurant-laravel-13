<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command\Response;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;

final readonly class TicketCancellationNoted implements CommandResponse
{
    public function __construct(public KitchenTicketId $ticketId) {}

    public function jsonSerialize(): array
    {
        return [
            'kitchenTicketId' => (string) $this->ticketId,
            'accepted' => true,
        ];
    }
}
