<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Metrics;

use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;
use App\Providers\Modules\SharedKernel\Application\Metrics\MetricsRecorder;
use App\Providers\Modules\SharedKernel\Application\Metrics\Series;
use Illuminate\Support\Facades\DB;

/**
 * One row per series, updated by a single atomic upsert: SQL adds the count and the sum and keeps the max in the same
 * statement, which a cache increment cannot do for the max. It survives `cache:clear` and is shared by the API and the workers.
 */
final class DatabaseMetricsRecorder implements MetricsRecorder
{
    public const TABLE = 'metrics';

    public function observe(Metric $metric, array $labels, float $milliseconds): void
    {
        $this->add($metric, $labels, 1, $milliseconds, $milliseconds);
    }

    public function count(Metric $metric, array $labels, int $by): void
    {
        $this->add($metric, $labels, $by, 0.0, 0.0);
    }

    public function series(): array
    {
        return DB::table(self::TABLE)
            ->orderBy('metric')
            ->orderBy('labels')
            ->get()
            ->map(fn (object $row): Series => new Series(
                Metric::from($row->metric),
                json_decode($row->labels, true, flags: JSON_THROW_ON_ERROR),
                (int) $row->occurrences,
                (float) $row->total_ms,
                (float) $row->max_ms,
            ))
            ->all();
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function add(Metric $metric, array $labels, int $occurrences, float $totalMs, float $maxMs): void
    {
        DB::table(self::TABLE)->upsert(
            [[
                'metric' => $metric->value,
                'labels' => SeriesKey::labels($labels),
                'occurrences' => $occurrences,
                'total_ms' => $totalMs,
                'max_ms' => $maxMs,
            ]],
            ['metric', 'labels'],
            [
                'occurrences' => DB::raw('occurrences + excluded.occurrences'),
                'total_ms' => DB::raw('total_ms + excluded.total_ms'),
                'max_ms' => DB::raw('max(max_ms, excluded.max_ms)'),
            ],
        );
    }
}
