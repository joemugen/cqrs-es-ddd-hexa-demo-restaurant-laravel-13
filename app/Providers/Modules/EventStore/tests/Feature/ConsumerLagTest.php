<?php

use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Metrics\ConsumerLag;
use App\Providers\Modules\SharedKernel\Domain\DomainEvent;
use App\Providers\Modules\SharedKernel\Domain\DomainEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamId;

function appendLagEvents(int $count): void
{
    foreach (range(1, $count) as $number) {
        app(EventStore::class)->append(new StreamId("lag-stream-{$number}"), 0, DomainEvents::of(DomainEvent::now('tests.lag', [])));
    }
}

describe('consumer lag', function (): void {
    it('is the number of positions each checkpoint has left to read', function (): void {
        appendLagEvents(5);
        $checkpoints = app(CheckpointStore::class);
        $first = app(EventStore::class)->readAllFrom(0)[0]->position;

        $checkpoints->put('kitchen.open-ticket', $first + 1);
        $checkpoints->put('billing.prepare-bill', $first + 4);

        expect(app(ConsumerLag::class)->byCheckpoint())
            ->toMatchArray(['billing.prepare-bill' => 0, 'kitchen.open-ticket' => 3]);
    });

    it('counts the whole log for a checkpoint that never moved', function (): void {
        appendLagEvents(2);
        app(CheckpointStore::class)->put('kitchen.open-ticket', 0);

        expect(app(ConsumerLag::class)->byCheckpoint())->toMatchArray(['kitchen.open-ticket' => 2]);
    });

    it('is nil on an empty log', function (): void {
        expect(array_sum(app(ConsumerLag::class)->byCheckpoint()))->toBe(0);
    });
});
