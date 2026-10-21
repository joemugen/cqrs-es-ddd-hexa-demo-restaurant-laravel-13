<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishesAdded;
use App\Providers\Modules\SharedKernel\Domain\AggregateId;
use App\Providers\Modules\SharedKernel\Domain\DomainEvent;
use App\Providers\Modules\SharedKernel\Domain\DomainEvents;
use App\Providers\Modules\SharedKernel\Domain\EventSourcedAggregate;
use App\Providers\Modules\SharedKernel\Domain\StreamId;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

final class KitchenTicket implements EventSourcedAggregate
{
    private const STREAM_PREFIX = 'kitchen-ticket-';

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
        $ticket->record(KitchenEvent::TICKET_OPENED, [
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

    /**
     * The dining room revised the order: the new dishes join the ticket, and a dish already on it is left alone.
     *
     * @param  list<TicketLine>  $lines
     */
    public function addDishes(array $lines): void
    {
        if ($lines === []) {
            throw new KitchenException('Add at least one dish to the ticket.');
        }

        $new = array_values(array_filter(
            $lines,
            fn (TicketLine $line): bool => ! isset($this->lines[(string) $line->lineId]),
        ));

        if ($new === []) {
            return;
        }

        $this->recordFact(new DishesAdded($this->id, $new));
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
            $this->record(KitchenEvent::DISH_FIRED, [
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
        $this->record(KitchenEvent::DISH_READY, [
            'ticketId' => (string) $this->id,
            'lineId' => $key,
            'dishName' => $line['dishName'],
            'partyRef' => (string) $this->partyRef,
            'tableLabel' => (string) $this->tableLabel,
            'quantity' => $line['quantity'],
        ]);

        if ($this->everyDishReady()) {
            $this->record(KitchenEvent::TICKET_COMPLETED, [
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
        return self::streamIdFor($this->id);
    }

    public static function streamIdFor(KitchenTicketId $id): StreamId
    {
        return new StreamId(self::STREAM_PREFIX.$id);
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

    private function recordFact(TypedDomainEvent $fact): void
    {
        $this->apply($fact->storedAs(), $fact->toStored());
        $this->pending = $this->pending->with(DomainEvent::recording($fact));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function apply(string $type, array $payload): void
    {
        if ($type === KitchenEvent::TICKET_OPENED) {
            $this->partyRef = new PartyRef((string) $payload['partyRef']);
            $this->tableLabel = new TableLabel((string) $payload['tableLabel']);
            $this->waitingFor($payload['lines']);

            return;
        }

        if ($type === DishesAdded::STORED_AS) {
            $this->waitingFor($payload['lines']);
            $this->completed = false;

            return;
        }

        if ($type === KitchenEvent::DISH_FIRED) {
            $this->lines[(string) $payload['lineId']]['fired'] = true;

            return;
        }

        if ($type === KitchenEvent::DISH_READY) {
            $this->lines[(string) $payload['lineId']]['ready'] = true;

            return;
        }

        if ($type === KitchenEvent::TICKET_COMPLETED) {
            $this->completed = true;
        }
    }

    /**
     * @param  list<array{lineId: string, dishName: string, station: string, cookingMinutes: int, quantity: int, course: string, allergens: list<string>}>  $lines
     */
    private function waitingFor(array $lines): void
    {
        foreach ($lines as $line) {
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
    }
}
