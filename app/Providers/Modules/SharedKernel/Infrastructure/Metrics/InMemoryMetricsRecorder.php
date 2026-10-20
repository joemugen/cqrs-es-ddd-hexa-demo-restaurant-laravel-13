<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Metrics;

use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;
use App\Providers\Modules\SharedKernel\Application\Metrics\MetricsRecorder;
use App\Providers\Modules\SharedKernel\Application\Metrics\Series;

/**
 * Keeps the series in the process: the double the tests bind in place of the database adapter.
 */
final class InMemoryMetricsRecorder implements MetricsRecorder
{
    /** @var array<string, Series> */
    private array $series = [];

    public function observe(Metric $metric, array $labels, float $milliseconds): void
    {
        $key = SeriesKey::of($metric, $labels);

        $this->series[$key] = ($this->series[$key] ?? new Series($metric, $labels))->observed($milliseconds);
    }

    public function count(Metric $metric, array $labels, int $by): void
    {
        $key = SeriesKey::of($metric, $labels);

        $this->series[$key] = ($this->series[$key] ?? new Series($metric, $labels))->counted($by);
    }

    public function series(): array
    {
        return array_values($this->series);
    }
}
