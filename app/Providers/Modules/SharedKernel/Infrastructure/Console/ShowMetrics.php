<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Console;

use App\Providers\Modules\SharedKernel\Application\Metrics\Sample;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\MetricsReport;
use Illuminate\Console\Command;

final class ShowMetrics extends Command
{
    protected $signature = 'metrics:show';

    protected $description = 'Show what the buses and the workers measured, and the lag of each consumer';

    public function handle(MetricsReport $report): int
    {
        $samples = $report->samples();

        if ($samples === []) {
            $this->components->info('No metric yet.');

            return self::SUCCESS;
        }

        $this->table(['Metric', 'Labels', 'Value'], array_map(
            fn (Sample $sample): array => [$sample->name, $this->labelsOf($sample), $sample->value],
            $samples,
        ));

        return self::SUCCESS;
    }

    private function labelsOf(Sample $sample): string
    {
        return implode(',', array_map(
            fn (string $name, string $value): string => "{$name}={$value}",
            array_keys($sample->labels),
            $sample->labels,
        ));
    }
}
