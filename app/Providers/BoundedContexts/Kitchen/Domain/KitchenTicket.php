<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

use App\Providers\BoundedContexts\SharedKernel\Domain\AggregateId;
use App\Providers\BoundedContexts\SharedKernel\Domain\DomainEvent;
use App\Providers\BoundedContexts\SharedKernel\Domain\DomainEvents;
use App\Providers\BoundedContexts\SharedKernel\Domain\EventSourcedAggregate;
use App\Providers\BoundedContexts\SharedKernel\Domain\StreamId;

final class KitchenTicket implements EventSourcedAggregate
{
    private DomainEvents $pending;

    /**
     * @var array<string, array{dishName: string, station: string, cookingMinutes: int, quantity: int, course: string, allergens: list<string>, fired: bool, ready: bool}>
     */
    private array $lines = [];

    private PartyRef $partyRef;

    private TableLabel $tableLabel;

    private bool $completed = false;

    private function __construct(
        private readonly KitchenTicketId $id,
        private int $version = 0,
    ) {
        $this->pending = new DomainEvents;
    }

    /**
     * @param  list<TicketLine>  $lines
     */
    public static function open(KitchenTicketId $id, PartyRef $partyRef, TableLabel $tableLabel, array $lines): self
    {
        if ($lines === []) {
            throw new KitchenException('A ticket needs at least one dish.');
        }

        $ticket = new self($id);
        $ticket->record('kitchen.ticket_opened', [
            'ticketId' => (string) $id,
            'partyRef' => (string) $partyRef,
            'tableLabel' => (string) $tableLabel,
            'lines' => array_map(fn (TicketLine $line): array => $line->toPayload(), $lines),
        ]);

        return $ticket;
    }

    public static function fromHistory(AggregateId $id, DomainEvents $history): self
    {
        assert($id instanceof KitchenTicketId);

        $ticket = new self($id);

        foreach ($history as $event) {
            $ticket->apply((string) $event->type, $event->payload);
            $ticket->version++;
        }

        return $ticket;
    }

    public function fireCourse(string $course): void
    {
        $this->guardOpen();
        $fired = false;

        foreach ($this->lines as $lineId => $line) {
            if ($line['course'] !== $course || $line['fired']) {
                continue;
            }

            $fired = true;
            $this->record('kitchen.dish_fired', [
                'ticketId' => (string) $this->id,
                'lineId' => $lineId,
                'course' => $course,
            ]);
        }

        if (! $fired) {
            throw new KitchenException("No {$course} is waiting on this ticket.");
        }
    }

    public function markReady(LineId $lineId): void
    {
        $this->guardOpen();
        $key = (string) $lineId;

        if (! isset($this->lines[$key])) {
            throw new KitchenException('That dish is not on this ticket.');
        }

        if (! $this->lines[$key]['fired']) {
            throw new KitchenException('Fire the dish before it can be ready.');
        }

        if ($this->lines[$key]['ready']) {
            throw new KitchenException('That dish is already ready.');
        }

        $line = $this->lines[$key];
        $this->record('kitchen.dish_ready', [
            'ticketId' => (string) $this->id,
            'lineId' => $key,
            'dishName' => $line['dishName'],
            'partyRef' => (string) $this->partyRef,
            'tableLabel' => (string) $this->tableLabel,
            'quantity' => $line['quantity'],
        ]);

        if ($this->everyDishReady()) {
            $this->record('kitchen.ticket_completed', [
                'ticketId' => (string) $this->id,
            ]);
        }
    }

    public function isComplete(): bool
    {
        return $this->completed;
    }

    public function id(): KitchenTicketId
    {
        return $this->id;
    }

    public function streamId(): StreamId
    {
        return new StreamId('kitchen-ticket-'.$this->id);
    }

    public function expectedVersion(): int
    {
        return $this->version;
    }

    public function pendingEvents(): DomainEvents
    {
        return $this->pending;
    }

    public function commit(int $version): void
    {
        $this->pending = new DomainEvents;
        $this->version = $version;
    }

    private function guardOpen(): void
    {
        if ($this->completed) {
            throw new KitchenException('A finished ticket cannot change.');
        }
    }

    private function everyDishReady(): bool
    {
        foreach ($this->lines as $line) {
            if (! $line['ready']) {
                return false;
            }
        }

        return $this->lines !== [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(string $type, array $payload): void
    {
        $this->apply($type, $payload);
        $this->pending = $this->pending->with(DomainEvent::now($type, $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function apply(string $type, array $payload): void
    {
        if ($type === 'kitchen.ticket_opened') {
            $this->partyRef = new PartyRef((string) $payload['partyRef']);
            $this->tableLabel = new TableLabel((string) $payload['tableLabel']);

            foreach ($payload['lines'] as $line) {
                $this->lines[(string) $line['lineId']] = [
                    'dishName' => (string) $line['dishName'],
                    'station' => (string) $line['station'],
                    'cookingMinutes' => (int) $line['cookingMinutes'],
                    'quantity' => (int) $line['quantity'],
                    'course' => (string) $line['course'],
                    'allergens' => array_values($line['allergens']),
                    'fired' => false,
                    'ready' => false,
                ];
            }

            return;
        }

        if ($type === 'kitchen.dish_fired') {
            $this->lines[(string) $payload['lineId']]['fired'] = true;

            return;
        }

        if ($type === 'kitchen.dish_ready') {
            $this->lines[(string) $payload['lineId']]['ready'] = true;

            return;
        }

        if ($type === 'kitchen.ticket_completed') {
            $this->completed = true;
        }
    }
}
