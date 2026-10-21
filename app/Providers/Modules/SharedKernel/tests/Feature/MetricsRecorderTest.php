<?php

use App\Providers\Modules\SharedKernel\Application\Metrics\Label;
use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;
use App\Providers\Modules\SharedKernel\Application\Metrics\MetricsRecorder;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\DatabaseMetricsRecorder;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\InMemoryMetricsRecorder;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\SafeMetricsRecorder;

describe('a metrics recorder', function (): void {
    $labels = [Label::TYPE => 'PayBill', Label::OUTCOME => 'success'];

    describe('behaves the same in memory and in the database', function () use ($labels): void {
        beforeEach(function (): void {
            $this->recorders = ['in memory' => new InMemoryMetricsRecorder, 'database' => new DatabaseMetricsRecorder];
        });

        it('adds up the occurrences, the durations and keeps the slowest', function () use ($labels): void {
            foreach ($this->recorders as $recorder) {
                $recorder->observe(Metric::Command, $labels, 12.5);
                $recorder->observe(Metric::Command, $labels, 30.0);
                $recorder->observe(Metric::Command, $labels, 7.5);

                $series = $recorder->series()[0];

                expect($series->metric)->toBe(Metric::Command)
                    ->and($series->labels)->toEqual($labels)
                    ->and($series->count)->toBe(3)
                    ->and($series->sumMs)->toBe(50.0)
                    ->and($series->maxMs)->toBe(30.0);
            }
        });

        it('keeps a series per metric and per set of labels', function () use ($labels): void {
            foreach ($this->recorders as $recorder) {
                $recorder->observe(Metric::Command, $labels, 1.0);
                $recorder->observe(Metric::Query, $labels, 1.0);
                $recorder->observe(Metric::Command, [Label::OUTCOME => 'success', Label::TYPE => 'PayBill'], 1.0);
                $recorder->observe(Metric::Command, [...$labels, Label::OUTCOME => 'failure'], 1.0);

                expect($recorder->series())->toHaveCount(3);
            }
        });

        it('counts occurrences that have no duration', function (): void {
            foreach ($this->recorders as $recorder) {
                $recorder->count(Metric::ConsumerEvents, [Label::CONSUMER => 'OpenKitchenTicketProcessor'], 3);
                $recorder->count(Metric::ConsumerEvents, [Label::CONSUMER => 'OpenKitchenTicketProcessor'], 2);

                $series = $recorder->series()[0];

                expect($series->count)->toBe(5)
                    ->and($series->sumMs)->toBe(0.0)
                    ->and($series->maxMs)->toBe(0.0);
            }
        });

        it('has nothing to report before anything is measured', function (): void {
            foreach ($this->recorders as $recorder) {
                expect($recorder->series())->toBe([]);
            }
        });
    });

    it('is the database adapter, guarded so that a write that fails does not fail the work', function (): void {
        expect(app(MetricsRecorder::class))->toBeInstanceOf(SafeMetricsRecorder::class);
    });
});
