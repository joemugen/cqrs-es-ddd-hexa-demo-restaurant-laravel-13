<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Integration;

use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishesAdded;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenEvent;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;

/**
 * What the kitchen tells the others when dishes join a ticket: which order, and what they need to know of the lines.
 * The stations and cooking times stay in the kitchen.
 */
#[PublishedAs(KitchenEvent::DISHES_ADDED, version: 1)]
#[Translates(DishesAdded::class)]
final readonly class DishesAddedContract implements IntegrationContract
{
    /**
     * @param  list<array{lineId: string, name: string, quantity: int, course: string}>  $lines
     */
    public function __construct(
        public string $tableOrderId,
        public array $lines,
    ) {}

    public static function from(DishesAdded $event): self
    {
        return new self(
            (string) $event->ticketId,
            array_map(fn (TicketLine $line): array => [
                'lineId' => (string) $line->lineId,
                'name' => (string) $line->dishName,
                'quantity' => $line->quantity,
                'course' => $line->course,
            ], $event->lines),
        );
    }

    public function toPayload(): array
    {
        return [
            'tableOrderId' => $this->tableOrderId,
            'lines' => $this->lines,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) $payload['tableOrderId'], $payload['lines']);
    }
}
