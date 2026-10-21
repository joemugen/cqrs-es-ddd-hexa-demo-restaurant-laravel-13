<?php

use App\Providers\Modules\EventStore\Application\IntegrationBus;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\IntegrationEvent;
use App\Providers\Modules\EventStore\Domain\OptimisticConcurrencyException;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Domain\EventType;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvent;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamId;

function eventStoreMessage(string $type, array $payload = []): RecordedEvent
{
    return RecordedEvent::now($type, $payload);
}

/**
 * @param  array<string, mixed>  $payload
 */
function pingContract(array $payload = []): IntegrationContract
{
    return new #[PublishedAs('tests.ping', version: 3)] class($payload) implements IntegrationContract
    {
        public function __construct(private readonly array $payload) {}

        public function toPayload(): array
        {
            return $this->payload;
        }

        public static function fromPayload(array $payload): static
        {
            return new self($payload);
        }
    };
}

describe('the event store', function () {
    test('a stream is appended and read back in version order', function () {
        $store = app(EventStore::class);

        $store->append(new StreamId('kitchen-ticket-1'), 0, RecordedEvents::of(
            eventStoreMessage('tests.opened', ['ticketId' => '1']),
        ));
        $store->append(new StreamId('kitchen-ticket-1'), 1, RecordedEvents::of(
            eventStoreMessage('tests.fired', ['lineId' => 'salad']),
            eventStoreMessage('tests.ready', ['lineId' => 'salad']),
        ));

        $stream = $store->readStream(new StreamId('kitchen-ticket-1'));

        expect($stream)->toHaveCount(3)
            ->and($stream[0]->version)->toBe(1)
            ->and($stream[0]->type->is('tests.opened'))->toBeTrue()
            ->and($stream[0]->payload)->toBe(['ticketId' => '1'])
            ->and($stream[1]->version)->toBe(2)
            ->and($stream[2]->version)->toBe(3)
            ->and($stream[2]->position)->toBeGreaterThan($stream[0]->position);
    });

    test('reading from a global position returns only later events', function () {
        $store = app(EventStore::class);
        $store->append(new StreamId('stream-a'), 0, RecordedEvents::of(eventStoreMessage('one')));
        $store->append(new StreamId('stream-b'), 0, RecordedEvents::of(eventStoreMessage('two')));

        $first = $store->readAllFrom(0);
        $rest = $store->readAllFrom($first[0]->position);

        expect($rest)->toHaveCount(1)
            ->and($rest[0]->type->is('two'))->toBeTrue()
            ->and((string) $rest[0]->streamId)->toBe('stream-b');
    });

    test('append refuses a stale expected version', function () {
        $store = app(EventStore::class);
        $store->append(new StreamId('kitchen-ticket-1'), 0, RecordedEvents::of(eventStoreMessage('tests.opened')));

        expect(fn () => $store->append(new StreamId('kitchen-ticket-1'), 0, RecordedEvents::of(eventStoreMessage('tests.fired'))))
            ->toThrow(OptimisticConcurrencyException::class);

        $store->append(new StreamId('kitchen-ticket-1'), 1, RecordedEvents::of(eventStoreMessage('tests.fired')));

        expect($store->readStream(new StreamId('kitchen-ticket-1')))->toHaveCount(2);
    });

    test('checkpoints remember how far a consumer has read', function () {
        $checkpoints = app(CheckpointStore::class);

        expect($checkpoints->get('foh.to-serve'))->toBe(0);

        $checkpoints->put('foh.to-serve', 4);
        $checkpoints->put('foh.to-serve', 9);

        expect($checkpoints->get('foh.to-serve'))->toBe(9)
            ->and($checkpoints->get('kitchen.screen'))->toBe(0);
    });

    test('a contract is told as an append-only fact other contexts can read, under the name and version it declares', function () {
        $bus = app(IntegrationBus::class);
        $seen = [];
        $bus->listen(new EventType('tests.ping'), function (IntegrationEvent $event) use (&$seen): void {
            $seen[] = $event->payload;
        });

        $bus->publish(pingContract(['tableOrderId' => 'order-1']));

        $events = app(EventStore::class)->readAllFrom(0);

        expect($seen)->toBe([['tableOrderId' => 'order-1']])
            ->and($events)->toHaveCount(1)
            ->and($events[0]->type->is('tests.ping'))->toBeTrue()
            ->and($events[0]->payload)->toBe(['tableOrderId' => 'order-1'])
            ->and($events[0]->contractVersion)->toBe(3);
    });

    test('append and publish return the position of the last fact they wrote', function () {
        $store = app(EventStore::class);

        $first = $store->append(new StreamId('stream-a'), 0, RecordedEvents::of(eventStoreMessage('one')));
        $last = $store->append(new StreamId('stream-b'), 0, RecordedEvents::of(eventStoreMessage('two'), eventStoreMessage('three')));
        $published = app(IntegrationBus::class)->publish(pingContract());

        $positions = array_map(fn ($event) => $event->position, $store->readAllFrom(0));

        expect($first)->toBe($positions[0])
            ->and($last)->toBe($positions[2])
            ->and($published)->toBe($positions[3])
            ->and($store->append(new StreamId('stream-c'), 0, new RecordedEvents))->toBe(0);
    });
});
