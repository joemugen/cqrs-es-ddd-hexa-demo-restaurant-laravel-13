<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure;

use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenNotFound;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\OptimisticConcurrencyException;
use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Application\Concurrency\ConcurrencyConflict;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvent;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamId;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;
use DateTimeImmutable;
use DateTimeZone;

final class EventStoreKitchenTicketRepository implements KitchenTicketRepository
{
    public function __construct(
        private EventStore $events,
        private ContractPublisher $contracts,
    ) {}

    public function exists(KitchenTicketId $id): bool
    {
        return $this->events->readStream($this->stream($id)) !== [];
    }

    public function get(KitchenTicketId $id): KitchenTicket
    {
        $history = $this->events->readStream($this->stream($id));

        if ($history === []) {
            throw new KitchenNotFound("Unknown kitchen ticket {$id}.");
        }

        return KitchenTicket::fromHistory($id, new RecordedEvents(array_map(
            fn (StoredEvent $event): RecordedEvent => new RecordedEvent(
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

        $this->contracts->publish($this->factsOf($pending));
        $ticket->commit($expected + count($pending));
    }

    /**
     * @return list<TypedDomainEvent>
     */
    private function factsOf(RecordedEvents $events): array
    {
        return array_values(array_filter(array_map(
            fn (RecordedEvent $event): ?TypedDomainEvent => $event->fact,
            iterator_to_array($events),
        )));
    }

    private function stream(KitchenTicketId $id): StreamId
    {
        return KitchenTicket::streamIdFor($id);
    }
}
