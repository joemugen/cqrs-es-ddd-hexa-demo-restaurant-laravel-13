<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Metrics;

use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;

final class SeriesKey
{
    /**
     * @param  array<string, string>  $labels
     */
    public static function of(Metric $metric, array $labels): string
    {
        return $metric->value.self::labels($labels);
    }

    /**
     * The labels in a fixed order, so the same set always names the same series.
     *
     * @param  array<string, string>  $labels
     */
    public static function labels(array $labels): string
    {
        ksort($labels);

        return json_encode($labels, JSON_THROW_ON_ERROR);
    }
}
