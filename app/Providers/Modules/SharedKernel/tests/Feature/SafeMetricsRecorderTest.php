<?php

use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;
use App\Providers\Modules\SharedKernel\Application\Metrics\MetricsRecorder;
use App\Providers\Modules\SharedKernel\Application\Metrics\Series;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\InMemoryMetricsRecorder;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\SafeMetricsRecorder;
use Illuminate\Contracts\Debug\ExceptionHandler;

final class UnwritableMetrics implements MetricsRecorder
{
    public function observe(Metric $metric, array $labels, float $milliseconds): void
    {
        throw new RuntimeException('database is locked');
    }

    public function count(Metric $metric, array $labels, int $by): void
    {
        throw new RuntimeException('database is locked');
    }

    public function series(): array
    {
        return [];
    }
}

describe('a metric that cannot be written', function (): void {
    beforeEach(function (): void {
        $this->reported = [];
        $this->app->instance(ExceptionHandler::class, new class($this->reported) implements ExceptionHandler
        {
            public function __construct(private array &$reported) {}

            public function report(Throwable $e): void
            {
                $this->reported[] = $e->getMessage();
            }

            public function shouldReport(Throwable $e): bool
            {
                return true;
            }

            public function render($request, Throwable $e)
            {
                throw $e;
            }

            public function renderForConsole($output, Throwable $e): void {}
        });
    });

    it('does not fail the message that was measured, and is reported', function (): void {
        $recorder = new SafeMetricsRecorder(new UnwritableMetrics);

        $recorder->observe(Metric::ConsumerPass, ['consumer' => 'x'], 1.0);
        $recorder->count(Metric::ConsumerEvents, ['consumer' => 'x'], 2);

        expect($this->reported)->toBe(['database is locked', 'database is locked']);
    });

    it('is written as usual when the recorder works', function (): void {
        $recorder = new SafeMetricsRecorder($inner = new InMemoryMetricsRecorder);

        $recorder->observe(Metric::ConsumerPass, ['consumer' => 'x'], 1.0);

        expect($recorder->series())->toEqual($inner->series())->and($recorder->series())->toHaveCount(1)
            ->and($recorder->series()[0])->toBeInstanceOf(Series::class);
    });
});
