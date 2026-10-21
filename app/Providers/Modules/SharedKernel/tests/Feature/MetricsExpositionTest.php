<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\Metrics\Label;
use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;
use App\Providers\Modules\SharedKernel\Application\Metrics\MetricsRecorder;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvent;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamId;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\MetricsReport;

describe('metrics exposition', function (): void {
    beforeEach(function (): void {
        $recorder = app(MetricsRecorder::class);
        $recorder->observe(Metric::Command, [Label::TYPE => 'PayBill', Label::OUTCOME => 'success'], 10.0);
        $recorder->observe(Metric::Command, [Label::TYPE => 'PayBill', Label::OUTCOME => 'success'], 30.0);
        $recorder->count(Metric::ConsumerEvents, [Label::CONSUMER => 'OpenKitchenTicketProcessor'], 4);
    });

    it('turns each series into a counter, a sum and a slowest', function (): void {
        $samples = collect(app(MetricsReport::class)->samples())
            ->mapWithKeys(fn ($sample): array => [$sample->name.json_encode($sample->labels) => [$sample->value, $sample->gauge]]);

        $command = json_encode([Label::OUTCOME => 'success', Label::TYPE => 'PayBill']);

        expect($samples['cqrs_command_total'.$command])->toBe([2, false])
            ->and($samples['cqrs_command_duration_ms_sum'.$command])->toBe([40.0, false])
            ->and($samples['cqrs_command_duration_ms_max'.$command])->toBe([30.0, true])
            ->and($samples['cqrs_consumer_events_total{"consumer":"OpenKitchenTicketProcessor"}'])->toBe([4, false])
            ->and($samples)->not->toHaveKey('cqrs_consumer_events_duration_ms_sum{"consumer":"OpenKitchenTicketProcessor"}');
    });

    it('adds the lag of each checkpoint, read when asked', function (): void {
        app(EventStore::class)->append(new StreamId('lag-1'), 0, RecordedEvents::of(RecordedEvent::now('tests.lag', [])));
        app(CheckpointStore::class)->put('kitchen.open-ticket', 0);

        $lag = collect(app(MetricsReport::class)->samples())->first(fn ($sample): bool => $sample->labels === [Label::CHECKPOINT => 'kitchen.open-ticket']);

        expect($lag->labels)->toBe([Label::CHECKPOINT => 'kitchen.open-ticket'])
            ->and($lag->value)->toBe(1)
            ->and($lag->gauge)->toBeTrue();
    });

    it('is printed by metrics:show', function (): void {
        $this->artisan('metrics:show')
            ->expectsOutputToContain('cqrs_command_total')
            ->expectsOutputToContain('type=PayBill')
            ->expectsOutputToContain('cqrs_consumer_events_total')
            ->assertSuccessful();
    });

    it('says so when nothing was measured', function (): void {
        DB::table('metrics')->delete();

        $this->artisan('metrics:show')->expectsOutputToContain('No metric yet.')->assertSuccessful();
    });

    it('is served as Prometheus text on GET /metrics', function (): void {
        $response = $this->get('/metrics');

        $response->assertOk()->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        expect($response->getContent())
            ->toContain("# TYPE cqrs_command_total counter\ncqrs_command_total{outcome=\"success\",type=\"PayBill\"} 2\n")
            ->toContain('cqrs_consumer_events_total{consumer="OpenKitchenTicketProcessor"} 4');
    });
});

describe('metrics of a real service', function (): void {
    it('count a request on the bus and a pass of the workers', function (): void {
        config(['consumption.mode' => 'worker']);
        $this->seed(FrontOfHouseSeeder::class);
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        app(EventStore::class)->append(new StreamId('noise-1'), 0, RecordedEvents::of(RecordedEvent::now('tests.noise', [])));

        $this->artisan('kitchen:consume --stop-when-empty')->assertSuccessful();

        $body = $this->get('/metrics')->getContent();

        expect($body)
            ->toContain('cqrs_command_total{outcome="success",type="App\\\\Providers\\\\BoundedContexts\\\\FrontOfHouse\\\\Application\\\\Command\\\\SeatGuests"} 1')
            ->toContain('cqrs_consumer_pass_total{consumer="OpenKitchenTicketProcessor",outcome="success"} 1')
            ->toContain('cqrs_consumer_lag{checkpoint="kitchen.open-ticket"} 0');
    });
});
