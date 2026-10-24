<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command\Response;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;

final readonly class MarkDishReadyAccepted implements CommandResponse
{
    /**
     * @param  int  $version  of the ticket after the command, sent back to the client as an ETag, not in the body
     */
    public function __construct(public KitchenTicketId $ticketId, public int $version) {}

    public function jsonSerialize(): array
    {
        return [
            'kitchenTicketId' => (string) $this->ticketId,
            'accepted' => true,
        ];
    }
}
