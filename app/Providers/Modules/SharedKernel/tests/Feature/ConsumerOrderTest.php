<?php

use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use App\Providers\Modules\SharedKernel\Domain\DomainEvent;
use App\Providers\Modules\SharedKernel\Domain\DomainEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamId;
use App\Providers\Modules\SharedKernel\Infrastructure\Eventing\ConsumerWorker;
use Illuminate\Support\Facades\Exceptions;

/**
 * Reads the log the way every real consumer does: from its checkpoint, in order, checkpoint last.
 * It writes down what it saw outside the database, so a rolled back pass still leaves a trace.
 */
final class RecordingConsumer implements EventConsumer
{
    /** @var list<int> */
    public array $seen = [];

    private bool $hasFailed = false;

    public function __construct(
        private EventStore $events,
        private CheckpointStore $checkpoints,
        private ?int $failsOnceAt = null,
    ) {}

    public function hasWork(): bool
    {
        return $this->events->hasEventsAfter($this->checkpoints->get('tests.recording'));
    }

    public function consume(): int
    {
        $position = $this->checkpoints->get('tests.recording');
        $handled = 0;

        foreach ($this->events->readAllFrom($position) as $event) {
            $this->seen[] = $event->position;

            if ($event->position === $this->failsOnceAt && ! $this->hasFailed) {
                $this->hasFailed = true;

                throw new RuntimeException("failed on position {$event->position}");
            }

            $handled++;
            $position = $event->position;
        }

        $this->checkpoints->put('tests.recording', $position);

        return $handled;
    }
}

/**
 * @param  list<string>  $streams  one event on each stream, in this order
 */
function appendOnStreams(array $streams): void
{
    foreach ($streams as $stream) {
        app(EventStore::class)->append(new StreamId($stream.'-'.bin2hex(random_bytes(3))), 0, DomainEvents::of(DomainEvent::now('tests.noise', [])));
    }
}

describe('the order in which a consumer sees events', function (): void {
    beforeEach(function (): void {
        config(['consumption.mode' => 'worker']);
        $this->worker = new ConsumerWorker(sleep: fn (float $seconds) => null);
        $this->consumer = new RecordingConsumer(app(EventStore::class), app(CheckpointStore::class));
    });

    it('is the order of the log, across streams and across passes', function (): void {
        appendOnStreams(['stream-b', 'stream-a', 'stream-c']);
        $this->worker->work([$this->consumer], stopWhenEmpty: true);

        appendOnStreams(['stream-a', 'stream-b']);
        $this->worker->work([$this->consumer], stopWhenEmpty: true);

        expect($this->consumer->seen)->toBe([1, 2, 3, 4, 5]);
    });

    it('never skips an event: after a failure the pass starts again where the checkpoint was', function (): void {
        Exceptions::fake();
        $consumer = new RecordingConsumer(app(EventStore::class), app(CheckpointStore::class), failsOnceAt: 3);
        appendOnStreams(['a', 'b', 'c', 'd', 'e']);

        $this->worker->work([$consumer], stopWhenEmpty: true);
        $this->worker->work([$consumer], stopWhenEmpty: true);

        expect(app(CheckpointStore::class)->get('tests.recording'))->toBe(5)
            ->and($consumer->seen)->toBe([1, 2, 3, 1, 2, 3, 4, 5]);
        Exceptions::assertReported(RuntimeException::class);
    });

    it('may see an event twice after a failure, so a consumer must be idempotent: delivery is at least once', function (): void {
        Exceptions::fake();
        $consumer = new RecordingConsumer(app(EventStore::class), app(CheckpointStore::class), failsOnceAt: 2);
        appendOnStreams(['a', 'b', 'c']);

        $this->worker->work([$consumer], stopWhenEmpty: true);
        $this->worker->work([$consumer], stopWhenEmpty: true);

        expect(array_count_values($consumer->seen)[1])->toBe(2);
    });
});
