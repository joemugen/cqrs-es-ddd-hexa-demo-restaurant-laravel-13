<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Consumed;

use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;
use App\Providers\BoundedContexts\Kitchen\Domain\PartyRef;
use App\Providers\BoundedContexts\Kitchen\Domain\TableLabel;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;

/**
 * Lines the dining room asks the kitchen to cook, for an order: the ticket they are for, the party and the lines in the
 * words of the kitchen.
 */
abstract readonly class OrderToCook implements ConsumedMessage
{
    /**
     * @param  list<OrderedLine>  $lines
     */
    final public function __construct(
        public KitchenTicketId $ticketId,
        public PartyRef $partyRef,
        public TableLabel $tableLabel,
        public array $lines,
    ) {}

    public static function fromPayload(array $payload): static
    {
        return new static(
            new KitchenTicketId((string) $payload['tableOrderId']),
            new PartyRef((string) $payload['tableId']),
            new TableLabel((string) $payload['tableLabel']),
            array_map(fn (array $line): OrderedLine => new OrderedLine(
                new LineId((string) $line['lineId']),
                new DishName((string) $line['name']),
                (int) $line['quantity'],
                (string) $line['course'],
                array_values($line['allergens']),
            ), $payload['lines']),
        );
    }
}
