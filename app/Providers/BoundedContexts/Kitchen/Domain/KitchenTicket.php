<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishesAdded;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishFired;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\DishReady;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\TicketCompleted;
use App\Providers\BoundedContexts\Kitchen\Domain\Event\TicketOpened;
use App\Providers\Modules\SharedKernel\Domain\AggregateId;
use App\Providers\Modules\SharedKernel\Domain\EventSourcedAggregate;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvent;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamedDomainEvent;
use App\Providers\Modules\SharedKernel\Domain\StreamId;

final class KitchenTicket implements EventSourcedAggregate
{
    private const STREAM_PREFIX = 'kitchen-ticket-';

    /** @var array<string, class-string<StreamedDomainEvent>> the facts of the stream, by the name they are stored under */
    private const FACTS = [
        TicketOpened::STORED_AS => TicketOpened::class,
        DishesAdded::STORED_AS => DishesAdded::class,
        DishFired::STORED_AS => DishFired::class,
        DishReady::STORED_AS => DishReady::class,
        TicketCompleted::STORED_AS => TicketCompleted::class,
    ];

    private RecordedEvents $pending;

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
        $this->pending = new RecordedEvents;
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
        $ticket->recordFact(new TicketOpened($id, $partyRef, $tableLabel, $lines));

        return $ticket;
    }

    public static function fromHistory(AggregateId $id, RecordedEvents $history): self
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
            $this->recordFact(new DishFired($this->id, new LineId((string) $lineId), $course));
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
        $this->recordFact(new DishReady(
            $this->id,
            $lineId,
            new DishName($line['dishName']),
            $this->partyRef,
            $this->tableLabel,
            $line['quantity'],
        ));

        if ($this->everyDishReady()) {
            $this->recordFact(new TicketCompleted($this->id));
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

    public function pendingEvents(): RecordedEvents
    {
        return $this->pending;
    }

    public function commit(int $version): void
    {
        $this->pending = new RecordedEvents;
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

    private function recordFact(StreamedDomainEvent $fact): void
    {
        $this->when($fact);
        $this->pending = $this->pending->with(RecordedEvent::recording($fact));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function apply(string $type, array $payload): void
    {
        $this->when(self::FACTS[$type]::fromStored($payload));
    }

    private function when(StreamedDomainEvent $fact): void
    {
        match (true) {
            $fact instanceof TicketOpened => $this->whenOpened($fact),
            $fact instanceof DishesAdded => $this->whenDishesAdded($fact),
            $fact instanceof DishFired => $this->lines[(string) $fact->lineId]['fired'] = true,
            $fact instanceof DishReady => $this->lines[(string) $fact->lineId]['ready'] = true,
            $fact instanceof TicketCompleted => $this->completed = true,
        };
    }

    private function whenOpened(TicketOpened $fact): void
    {
        $this->partyRef = $fact->partyRef;
        $this->tableLabel = $fact->tableLabel;
        $this->waitingFor($fact->lines);
    }

    private function whenDishesAdded(DishesAdded $fact): void
    {
        $this->waitingFor($fact->lines);
        $this->completed = false;
    }

    /**
     * @param  list<TicketLine>  $lines
     */
    private function waitingFor(array $lines): void
    {
        foreach ($lines as $line) {
            $this->lines[(string) $line->lineId] = [
                'dishName' => (string) $line->dishName,
                'station' => $line->station,
                'cookingMinutes' => $line->cookingMinutes,
                'quantity' => $line->quantity,
                'course' => $line->course,
                'allergens' => $line->allergens,
                'fired' => false,
                'ready' => false,
            ];
        }
    }
}
