<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Http;

use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\MetricsReport;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\PrometheusText;
use Illuminate\Http\Response;

final readonly class ShowMetricsController
{
    public function __invoke(MetricsReport $report): Response
    {
        return response(PrometheusText::render($report->samples()), headers: ['Content-Type' => PrometheusText::CONTENT_TYPE]);
    }
}
