<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Metrics;

/**
 * What was measured for one metric and one set of labels: how many times, the sum of the durations and the slowest.
 */
final readonly class Series
{
    /**
     * @param  array<string, string>  $labels
     */
    public function __construct(
        public Metric $metric,
        public array $labels,
        public int $count = 0,
        public float $sumMs = 0.0,
        public float $maxMs = 0.0,
    ) {}

    public function observed(float $milliseconds): self
    {
        return new self($this->metric, $this->labels, $this->count + 1, $this->sumMs + $milliseconds, max($this->maxMs, $milliseconds));
    }

    public function counted(int $by): self
    {
        return new self($this->metric, $this->labels, $this->count + $by, $this->sumMs, $this->maxMs);
    }
}
