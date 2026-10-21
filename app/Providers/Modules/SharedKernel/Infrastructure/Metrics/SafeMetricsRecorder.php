<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Metrics;

use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;
use App\Providers\Modules\SharedKernel\Application\Metrics\MetricsRecorder;
use Throwable;

/**
 * Observability never breaks the business: a metric that cannot be written is reported, and the work it measured stands.
 */
final readonly class SafeMetricsRecorder implements MetricsRecorder
{
    public function __construct(private MetricsRecorder $recorder) {}

    public function observe(Metric $metric, array $labels, float $milliseconds): void
    {
        $this->swallowing(fn () => $this->recorder->observe($metric, $labels, $milliseconds));
    }

    public function count(Metric $metric, array $labels, int $by): void
    {
        $this->swallowing(fn () => $this->recorder->count($metric, $labels, $by));
    }

    public function series(): array
    {
        return $this->recorder->series();
    }

    private function swallowing(callable $write): void
    {
        try {
            $write();
        } catch (Throwable $failure) {
            report($failure);
        }
    }
}
