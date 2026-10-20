<?php

use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use App\Providers\Modules\SharedKernel\Application\Metrics\Label;
use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;
use App\Providers\Modules\SharedKernel\Infrastructure\Eventing\ConsumerWorker;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\InMemoryMetricsRecorder;
use Illuminate\Support\Facades\Exceptions;

abstract class MeteredConsumer implements EventConsumer
{
    /** @param list<int|Throwable> $script what each call does: handle that many events, or fail */
    public function __construct(private array $script, private bool $hasWork = true) {}

    public function hasWork(): bool
    {
        return $this->hasWork;
    }

    public function consume(): int
    {
        $step = array_shift($this->script) ?? 0;

        throw_if($step instanceof Throwable, $step);

        return $step;
    }
}

final class MeteredKitchenConsumer extends MeteredConsumer {}

final class MeteredBillingConsumer extends MeteredConsumer {}

function meteredConsumer(InMemoryMetricsRecorder $recorder, Metric $metric, string $consumer, ?string $outcome = null): ?object
{
    foreach ($recorder->series() as $series) {
        $matches = $series->metric === $metric
            && $series->labels[Label::CONSUMER] === $consumer
            && ($outcome === null || $series->labels[Label::OUTCOME] === $outcome);

        if ($matches) {
            return $series;
        }
    }

    return null;
}

describe('consumer metrics', function (): void {
    beforeEach(function (): void {
        $this->recorder = new InMemoryMetricsRecorder;
        $this->worker = new ConsumerWorker(sleep: fn (float $seconds) => null, metrics: $this->recorder);
    });

    it('counts the events each consumer handled', function (): void {
        $this->worker->work(
            [new MeteredKitchenConsumer([2, 1]), new MeteredBillingConsumer([0, 3])],
            stopWhenEmpty: true,
        );

        expect(meteredConsumer($this->recorder, Metric::ConsumerEvents, MeteredKitchenConsumer::class)->count)->toBe(3)
            ->and(meteredConsumer($this->recorder, Metric::ConsumerEvents, MeteredBillingConsumer::class)->count)->toBe(3);
    });

    it('times every pass that had something to read', function (): void {
        $this->worker->work([new MeteredKitchenConsumer([1])], stopWhenEmpty: true);

        $passes = meteredConsumer($this->recorder, Metric::ConsumerPass, MeteredKitchenConsumer::class, 'success');

        expect($passes->count)->toBe(2)
            ->and($passes->maxMs)->toBeGreaterThan(0.0)
            ->and($passes->sumMs)->toBeGreaterThanOrEqual($passes->maxMs);
    });

    it('counts a failed pass apart, with no event handled', function (): void {
        Exceptions::fake();

        $this->worker->work(
            [new MeteredKitchenConsumer([new RuntimeException('Boom.'), 2]), new MeteredBillingConsumer([1])],
            stopWhenEmpty: true,
        );

        expect(meteredConsumer($this->recorder, Metric::ConsumerPass, MeteredKitchenConsumer::class, 'failure')->count)->toBe(1)
            ->and(meteredConsumer($this->recorder, Metric::ConsumerEvents, MeteredKitchenConsumer::class)->count)->toBe(2);
    });

    it('records nothing for a consumer that has nothing to read', function (): void {
        $this->worker->work([new MeteredKitchenConsumer([], hasWork: false)], stopWhenEmpty: true);

        expect($this->recorder->series())->toBe([]);
    });
});
