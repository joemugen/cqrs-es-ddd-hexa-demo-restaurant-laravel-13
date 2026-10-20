<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Metrics;

use App\Providers\Modules\SharedKernel\Application\Metrics\Sample;

/**
 * The text exposition format Prometheus scrapes: a TYPE line per metric, then one line per sample.
 */
final class PrometheusText
{
    public const CONTENT_TYPE = 'text/plain; version=0.0.4; charset=utf-8';

    /**
     * @param  list<Sample>  $samples
     */
    public static function render(array $samples): string
    {
        $byName = [];

        foreach ($samples as $sample) {
            $byName[$sample->name][] = $sample;
        }

        $text = '';

        foreach ($byName as $name => $named) {
            $text .= '# TYPE '.$name.' '.($named[0]->gauge ? 'gauge' : 'counter')."\n";

            foreach ($named as $sample) {
                $text .= $name.self::labels($sample->labels).' '.self::number($sample->value)."\n";
            }
        }

        return $text;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private static function labels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        ksort($labels);

        return '{'.implode(',', array_map(
            fn (string $name, string $value): string => $name.'="'.str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value).'"',
            array_keys($labels),
            $labels,
        )).'}';
    }

    private static function number(int|float $value): string
    {
        return is_int($value) ? (string) $value : rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
