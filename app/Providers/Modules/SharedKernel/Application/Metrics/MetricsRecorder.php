<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Metrics;

interface MetricsRecorder
{
    /**
     * One more occurrence that lasted $milliseconds.
     *
     * @param  array<string, string>  $labels
     */
    public function observe(Metric $metric, array $labels, float $milliseconds): void;

    /**
     * $by more occurrences, with no duration.
     *
     * @param  array<string, string>  $labels
     */
    public function count(Metric $metric, array $labels, int $by): void;

    /**
     * @return list<Series>
     */
    public function series(): array;
}
