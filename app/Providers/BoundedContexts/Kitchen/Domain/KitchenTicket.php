<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

use DateTimeImmutable;
use DateTimeZone;

final class KitchenTicket
{
    /** @var list<array{type: string, payload: array<string, mixed>, occurredAt: string}> */
    private array $pending = [];

    /**
     * @var array<string, array{dishName: string, station: string, cookingMinutes: int, quantity: int, course: string, allergens: list<string>, fired: bool, ready: bool}>
     */
    private array $lines = [];

    private string $partyRef = '';

    private string $tableLabel = '';

    private bool $completed = false;

    private function __construct(
        private readonly string $id,
        private int $version = 0,
    ) {}

    /**
     * @param  list<array{lineId: string, dishName: string, station: string, cookingMinutes: int, quantity: int, course: string, allergens: list<string>}>  $lines
     */
    public static function open(string $id, string $partyRef, string $tableLabel, array $lines): self
    {
        if ($lines === []) {
            throw new KitchenException('A ticket needs at least one dish.');
        }

        $ticket = new self($id);
        $ticket->record('kitchen.ticket_opened', [
            'ticketId' => $id,
            'partyRef' => $partyRef,
            'tableLabel' => $tableLabel,
            'lines' => $lines,
        ]);

        return $ticket;
    }

    /**
     * @param  list<array{type: string, payload: array<string, mixed>}>  $history
     */
    public static function fromHistory(string $id, array $history): self
    {
        $ticket = new self($id);

        foreach ($history as $event) {
            $ticket->apply($event['type'], $event['payload']);
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
                'ticketId' => $this->id,
                'lineId' => $lineId,
                'course' => $course,
            ]);
        }

        if (! $fired) {
            throw new KitchenException("No {$course} is waiting on this ticket.");
        }
    }

    public function markReady(string $lineId): void
    {
        $this->guardOpen();

        if (! isset($this->lines[$lineId])) {
            throw new KitchenException('That dish is not on this ticket.');
        }

        if (! $this->lines[$lineId]['fired']) {
            throw new KitchenException('Fire the dish before it can be ready.');
        }

        if ($this->lines[$lineId]['ready']) {
            throw new KitchenException('That dish is already ready.');
        }

        $line = $this->lines[$lineId];
        $this->record('kitchen.dish_ready', [
            'ticketId' => $this->id,
            'lineId' => $lineId,
            'dishName' => $line['dishName'],
            'partyRef' => $this->partyRef,
            'tableLabel' => $this->tableLabel,
            'quantity' => $line['quantity'],
        ]);

        if ($this->everyDishReady()) {
            $this->record('kitchen.ticket_completed', [
                'ticketId' => $this->id,
            ]);
        }
    }

    public function isComplete(): bool
    {
        return $this->completed;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function streamId(): string
    {
        return 'kitchen-ticket-'.$this->id;
    }

    public function expectedVersion(): int
    {
        return $this->version;
    }

    /**
     * @return list<array{type: string, payload: array<string, mixed>, occurredAt: string}>
     */
    public function pendingEvents(): array
    {
        return $this->pending;
    }

    public function commit(int $version): void
    {
        $this->pending = [];
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
        $this->pending[] = [
            'type' => $type,
            'payload' => $payload,
            'occurredAt' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function apply(string $type, array $payload): void
    {
        if ($type === 'kitchen.ticket_opened') {
            $this->partyRef = (string) $payload['partyRef'];
            $this->tableLabel = (string) $payload['tableLabel'];

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
