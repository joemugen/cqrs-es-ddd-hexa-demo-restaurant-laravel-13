<?php

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\EventStore\Domain\CheckpointStore;
use App\Providers\BoundedContexts\EventStore\Domain\EventMessage;
use App\Providers\BoundedContexts\EventStore\Domain\EventStore;
use App\Providers\BoundedContexts\EventStore\Domain\OptimisticConcurrencyException;

function eventStoreMessage(string $type, array $payload = []): EventMessage
{
    return new class($type, $payload) implements EventMessage
    {
        public function __construct(
            private string $type,
            private array $payload,
        ) {}

        public function type(): string
        {
            return $this->type;
        }

        public function payload(): array
        {
            return $this->payload;
        }

        public function occurredAt(): string
        {
            return '2026-10-21 12:00:00';
        }
    };
}

test('a stream is appended and read back in version order', function () {
    $store = app(EventStore::class);

    $store->append('kitchen-ticket-1', 0, [
        eventStoreMessage('tests.opened', ['ticketId' => '1']),
    ]);
    $store->append('kitchen-ticket-1', 1, [
        eventStoreMessage('tests.fired', ['lineId' => 'salad']),
        eventStoreMessage('tests.ready', ['lineId' => 'salad']),
    ]);

    $stream = $store->readStream('kitchen-ticket-1');

    expect($stream)->toHaveCount(3)
        ->and($stream[0]->version)->toBe(1)
        ->and($stream[0]->type)->toBe('tests.opened')
        ->and($stream[0]->payload)->toBe(['ticketId' => '1'])
        ->and($stream[1]->version)->toBe(2)
        ->and($stream[2]->version)->toBe(3)
        ->and($stream[2]->position)->toBeGreaterThan($stream[0]->position);
});

test('reading from a global position returns only later events', function () {
    $store = app(EventStore::class);
    $store->append('stream-a', 0, [eventStoreMessage('one')]);
    $store->append('stream-b', 0, [eventStoreMessage('two')]);

    $first = $store->readAllFrom(0);
    $rest = $store->readAllFrom($first[0]->position);

    expect($rest)->toHaveCount(1)
        ->and($rest[0]->type)->toBe('two')
        ->and($rest[0]->streamId)->toBe('stream-b');
});

test('append refuses a stale expected version', function () {
    $store = app(EventStore::class);
    $store->append('kitchen-ticket-1', 0, [eventStoreMessage('tests.opened')]);

    expect(fn () => $store->append('kitchen-ticket-1', 0, [eventStoreMessage('tests.fired')]))
        ->toThrow(OptimisticConcurrencyException::class);

    $store->append('kitchen-ticket-1', 1, [eventStoreMessage('tests.fired')]);

    expect($store->readStream('kitchen-ticket-1'))->toHaveCount(2);
});

test('checkpoints remember how far a consumer has read', function () {
    $checkpoints = app(CheckpointStore::class);

    expect($checkpoints->get('foh.to-serve'))->toBe(0);

    $checkpoints->put('foh.to-serve', 4);
    $checkpoints->put('foh.to-serve', 9);

    expect($checkpoints->get('foh.to-serve'))->toBe(9)
        ->and($checkpoints->get('kitchen.screen'))->toBe(0);
});

test('an integration event is an append-only fact other contexts can read', function () {
    $bus = app(IntegrationBus::class);
    $seen = [];
    $bus->listen('tests.ping', function (array $payload) use (&$seen): void {
        $seen[] = $payload;
    });

    $bus->publish('tests.ping', ['tableOrderId' => 'order-1']);

    $events = app(EventStore::class)->readAllFrom(0);

    expect($seen)->toBe([['tableOrderId' => 'order-1']])
        ->and($events)->toHaveCount(1)
        ->and($events[0]->type)->toBe('tests.ping')
        ->and($events[0]->payload)->toBe(['tableOrderId' => 'order-1']);
});
