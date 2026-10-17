<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure;

use App\Providers\BoundedContexts\EventStore\Domain\EventStore;
use App\Providers\BoundedContexts\EventStore\Domain\OptimisticConcurrencyException;
use App\Providers\BoundedContexts\EventStore\Infrastructure\GenericMessage;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicket;

final class EventStoreKitchenTicketRepository implements KitchenTicketRepository
{
    public function __construct(private EventStore $events) {}

    public function exists(string $id): bool
    {
        return $this->events->readStream($this->stream($id)) !== [];
    }

    public function get(string $id): KitchenTicket
    {
        $history = $this->events->readStream($this->stream($id));

        if ($history === []) {
            throw new KitchenException("Unknown kitchen ticket {$id}.", 404);
        }

        return KitchenTicket::fromHistory($id, array_map(fn ($event): array => [
            'type' => $event->type,
            'payload' => $event->payload,
        ], $history));
    }

    public function save(KitchenTicket $ticket): void
    {
        $pending = $ticket->pendingEvents();

        if ($pending === []) {
            return;
        }

        $expected = $ticket->expectedVersion();

        try {
            $this->events->append(
                $ticket->streamId(),
                $expected,
                array_map(
                    fn (array $event): GenericMessage => new GenericMessage($event['type'], $event['payload'], $event['occurredAt']),
                    $pending,
                ),
            );
        } catch (OptimisticConcurrencyException $exception) {
            throw new KitchenException($exception->getMessage(), 409);
        }

        $ticket->commit($expected + count($pending));
    }

    private function stream(string $id): string
    {
        return 'kitchen-ticket-'.$id;
    }
}
