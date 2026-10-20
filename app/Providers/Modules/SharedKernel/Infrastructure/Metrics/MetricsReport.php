<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Metrics;

use App\Providers\Modules\SharedKernel\Application\Metrics\ConsumerLag;
use App\Providers\Modules\SharedKernel\Application\Metrics\Label;
use App\Providers\Modules\SharedKernel\Application\Metrics\MetricsRecorder;
use App\Providers\Modules\SharedKernel\Application\Metrics\Sample;
use App\Providers\Modules\SharedKernel\Application\Metrics\Series;

/**
 * What a reader sees: every recorded series as counters and gauges, plus the lag of each checkpoint.
 * The lag is read when asked, so a worker that stopped still shows a lag that grows.
 */
final readonly class MetricsReport
{
    public const CONSUMER_LAG = 'cqrs_consumer_lag';

    public function __construct(private MetricsRecorder $metrics, private ConsumerLag $lag) {}

    /**
     * @return list<Sample>
     */
    public function samples(): array
    {
        return [
            ...array_merge(...array_map($this->samplesOf(...), $this->metrics->series())),
            ...$this->lagSamples(),
        ];
    }

    /**
     * @return list<Sample>
     */
    private function samplesOf(Series $series): array
    {
        $name = $series->metric->value;
        $samples = [new Sample($name.'_total', $series->labels, $series->count)];

        if ($series->metric->isTimed()) {
            $samples[] = new Sample($name.'_duration_ms_sum', $series->labels, $series->sumMs);
            $samples[] = new Sample($name.'_duration_ms_max', $series->labels, $series->maxMs, gauge: true);
        }

        return $samples;
    }

    /**
     * @return list<Sample>
     */
    private function lagSamples(): array
    {
        $samples = [];

        foreach ($this->lag->byCheckpoint() as $checkpoint => $remaining) {
            $samples[] = new Sample(self::CONSUMER_LAG, [Label::CHECKPOINT => $checkpoint], $remaining, gauge: true);
        }

        return $samples;
    }
}
