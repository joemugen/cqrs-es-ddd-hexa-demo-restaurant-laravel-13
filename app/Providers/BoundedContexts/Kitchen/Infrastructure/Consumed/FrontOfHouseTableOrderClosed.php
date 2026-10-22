<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Consumed;

use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * The guests of an order paid and left: the kitchen stops its ticket, which has the id of the order.
 */
#[Consumes(ConsumedEvents::FRONT_OF_HOUSE_TABLE_ORDER_CLOSED, version: 1)]
final readonly class FrontOfHouseTableOrderClosed implements ConsumedMessage
{
    public function __construct(public KitchenTicketId $ticketId) {}

    public static function fromPayload(array $payload): static
    {
        return new self(new KitchenTicketId((string) $payload['tableOrderId']));
    }
}
