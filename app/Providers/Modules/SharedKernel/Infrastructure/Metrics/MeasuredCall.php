<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Metrics;

use App\Providers\Modules\SharedKernel\Application\Metrics\Label;
use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;
use App\Providers\Modules\SharedKernel\Application\Metrics\MetricsRecorder;
use App\Providers\Modules\SharedKernel\Application\Metrics\Outcome;
use Closure;
use Throwable;

/**
 * Runs a call and records how long it took and how it ended, then lets the result or the failure through.
 */
final readonly class MeasuredCall
{
    public function __construct(private MetricsRecorder $metrics) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T
     */
    public function run(Metric $metric, string $type, Closure $call): mixed
    {
        $start = hrtime(true);

        try {
            $result = $call();
        } catch (Throwable $failure) {
            $this->record($metric, $type, Outcome::of($failure), $start);

            throw $failure;
        }

        $this->record($metric, $type, Outcome::Success, $start);

        return $result;
    }

    private function record(Metric $metric, string $type, Outcome $outcome, int $start): void
    {
        $this->metrics->observe(
            $metric,
            [Label::TYPE => $type, Label::OUTCOME => $outcome->value],
            (hrtime(true) - $start) / 1_000_000,
        );
    }
}
