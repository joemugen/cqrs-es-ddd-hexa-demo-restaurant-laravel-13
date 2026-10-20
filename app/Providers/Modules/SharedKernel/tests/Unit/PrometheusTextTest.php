<?php

use App\Providers\Modules\SharedKernel\Application\Metrics\Sample;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\PrometheusText;

describe('the Prometheus text format', function (): void {
    it('declares each metric once, then lists its samples', function (): void {
        $text = PrometheusText::render([
            new Sample('cqrs_command_total', ['type' => 'PayBill', 'outcome' => 'success'], 3),
            new Sample('cqrs_command_total', ['type' => 'PayBill', 'outcome' => 'failure'], 1),
            new Sample('cqrs_command_duration_ms_max', ['type' => 'PayBill', 'outcome' => 'success'], 12.5, gauge: true),
        ]);

        expect($text)->toBe(<<<'TEXT'
        # TYPE cqrs_command_total counter
        cqrs_command_total{outcome="success",type="PayBill"} 3
        cqrs_command_total{outcome="failure",type="PayBill"} 1
        # TYPE cqrs_command_duration_ms_max gauge
        cqrs_command_duration_ms_max{outcome="success",type="PayBill"} 12.5

        TEXT);
    });

    it('escapes what a label value may hold', function (): void {
        $text = PrometheusText::render([
            new Sample('cqrs_command_total', ['type' => 'App\\Billing\\PayBill "now"'], 1),
        ]);

        expect($text)->toContain('{type="App\\\\Billing\\\\PayBill \\"now\\""} 1');
    });

    it('renders a metric without label as a bare name', function (): void {
        expect(PrometheusText::render([new Sample('cqrs_up', [], 1, gauge: true)]))
            ->toBe("# TYPE cqrs_up gauge\ncqrs_up 1\n");
    });

    it('is empty when nothing was measured', function (): void {
        expect(PrometheusText::render([]))->toBe('');
    });
});
