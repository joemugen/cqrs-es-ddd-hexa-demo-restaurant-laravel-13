<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure;

use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\OptimisticConcurrencyException;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Domain\ConcurrencyConflict;
use App\Providers\Modules\SharedKernel\Domain\DomainEvent;
use App\Providers\Modules\SharedKernel\Domain\DomainEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamId;
use DateTimeImmutable;
use DateTimeZone;

final class EventStoreKitchenTicketRepository implements KitchenTicketRepository
{
    public function __construct(private EventStore $events) {}

    public function exists(KitchenTicketId $id): bool
    {
        return $this->events->readStream($this->stream($id)) !== [];
    }

    public function get(KitchenTicketId $id): KitchenTicket
    {
        $history = $this->events->readStream($this->stream($id));

        if ($history === []) {
            throw new KitchenException("Unknown kitchen ticket {$id}.", 404);
        }

        return KitchenTicket::fromHistory($id, new DomainEvents(array_map(
            fn (StoredEvent $event): DomainEvent => new DomainEvent(
                $event->type,
                $event->payload,
                new DateTimeImmutable($event->occurredAt, new DateTimeZone('UTC')),
            ),
            $history,
        )));
    }

    public function save(KitchenTicket $ticket): void
    {
        $pending = $ticket->pendingEvents();

        if ($pending->isEmpty()) {
            return;
        }

        $expected = $ticket->expectedVersion();

        try {
            $this->events->append($ticket->streamId(), $expected, $pending);
        } catch (OptimisticConcurrencyException $exception) {
            throw new ConcurrencyConflict($exception->getMessage());
        }

        $ticket->commit($expected + count($pending));
    }

    private function stream(KitchenTicketId $id): StreamId
    {
        return KitchenTicket::streamIdFor($id);
    }
}
