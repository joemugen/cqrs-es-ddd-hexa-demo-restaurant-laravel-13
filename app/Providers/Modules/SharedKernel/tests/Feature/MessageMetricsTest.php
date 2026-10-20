<?php

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\SeatGuests;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListFloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\FrontOfHouseSeeder;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenCommandBus;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\Modules\SharedKernel\Application\Bus\Exception\NotAuthorized;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use App\Providers\Modules\SharedKernel\Application\Metrics\Label;
use App\Providers\Modules\SharedKernel\Application\Metrics\Metric;
use App\Providers\Modules\SharedKernel\Application\Metrics\MetricsRecorder;
use App\Providers\Modules\SharedKernel\Application\Metrics\Outcome;
use App\Providers\Modules\SharedKernel\Application\Metrics\Series;
use App\Providers\Modules\SharedKernel\Application\Query;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;
use App\Providers\Modules\SharedKernel\Domain\ConcurrencyConflict;
use App\Providers\Modules\SharedKernel\Domain\PreconditionFailed;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\MetricsCommandMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\MetricsQueryMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\RetryOnConflict;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\MiddlewareCommandBus;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\MiddlewareQueryBus;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\InMemoryMetricsRecorder;

final readonly class MeteredCommand implements Command
{
    /** @param ArrayObject<int, Throwable> $failures one is thrown per call, until none is left */
    public function __construct(public ArrayObject $failures = new ArrayObject, public int $microseconds = 0) {}
}

final readonly class MeteredAnswer implements CommandResponse, QueryResponse
{
    public function jsonSerialize(): array
    {
        return [];
    }
}

#[AsCommandHandler(MeteredCommand::class)]
final readonly class MeteredCommandHandler implements CommandHandler
{
    public function __invoke(MeteredCommand $command): MeteredAnswer
    {
        $failures = $command->failures->getArrayCopy();
        $failure = array_shift($failures);
        $command->failures->exchangeArray($failures);

        if ($failure !== null) {
            throw $failure;
        }

        usleep($command->microseconds);

        return new MeteredAnswer;
    }
}

final readonly class MeteredQuery implements Query
{
    public function __construct(public ?Throwable $failure = null) {}
}

#[AsQueryHandler(MeteredQuery::class)]
final readonly class MeteredQueryHandler implements QueryHandler
{
    public function __invoke(MeteredQuery $query): MeteredAnswer
    {
        throw_if($query->failure !== null, $query->failure);

        return new MeteredAnswer;
    }
}

final class MeteredCommandBus extends MiddlewareCommandBus
{
    protected function handlers(): array
    {
        return [MeteredCommandHandler::class];
    }

    protected function middleware(): array
    {
        return [MetricsCommandMiddleware::class, RetryOnConflict::class];
    }
}

final class MeteredQueryBus extends MiddlewareQueryBus
{
    protected function handlers(): array
    {
        return [MeteredQueryHandler::class];
    }

    protected function middleware(): array
    {
        return [MetricsQueryMiddleware::class];
    }
}

function meteredSeries(MetricsRecorder $recorder, Metric $metric, array $labels): ?Series
{
    foreach ($recorder->series() as $series) {
        if ($series->metric === $metric && $series->labels === $labels) {
            return $series;
        }
    }

    return null;
}

function meteredCommandLabels(Outcome $outcome): array
{
    return [Label::TYPE => MeteredCommand::class, Label::OUTCOME => $outcome->value];
}

describe('command metrics', function (): void {
    beforeEach(function (): void {
        $this->recorder = new InMemoryMetricsRecorder;
        $this->app->instance(MetricsRecorder::class, $this->recorder);
    });

    it('counts a handled command and keeps its duration', function (): void {
        app(MeteredCommandBus::class)->dispatch(new MeteredCommand(microseconds: 2_000));

        $series = meteredSeries($this->recorder, Metric::Command, meteredCommandLabels(Outcome::Success));

        expect($series->count)->toBe(1)
            ->and($series->sumMs)->toBeGreaterThanOrEqual(2.0)
            ->and($series->maxMs)->toBe($series->sumMs);
    });

    it('keeps the sum and the slowest of several commands', function (): void {
        app(MeteredCommandBus::class)->dispatch(new MeteredCommand(microseconds: 1_000));
        app(MeteredCommandBus::class)->dispatch(new MeteredCommand(microseconds: 4_000));

        $series = meteredSeries($this->recorder, Metric::Command, meteredCommandLabels(Outcome::Success));

        expect($series->count)->toBe(2)
            ->and($series->maxMs)->toBeGreaterThanOrEqual(4.0)
            ->and($series->sumMs)->toBeGreaterThan($series->maxMs);
    });

    it('tells each way a command ends apart', function (Throwable $failure, Outcome $outcome): void {
        expect(fn () => app(MeteredCommandBus::class)->dispatch(new MeteredCommand(new ArrayObject([$failure, $failure, $failure]))))
            ->toThrow($failure::class);

        expect(meteredSeries($this->recorder, Metric::Command, meteredCommandLabels($outcome))->count)->toBe(1);
    })->with([
        'a business refusal' => [new NotAuthorized, Outcome::Refused],
        'a write conflict' => [new ConcurrencyConflict('Someone else wrote.'), Outcome::Conflict],
        'a stale precondition' => [new PreconditionFailed('The ticket moved on.'), Outcome::PreconditionFailed],
        'an unexpected failure' => [new RuntimeException('Boom.'), Outcome::Failure],
    ]);

    it('counts a retried command once, as the caller sees it', function (): void {
        $conflict = new ConcurrencyConflict('Someone else wrote.');

        app(MeteredCommandBus::class)->dispatch(new MeteredCommand(new ArrayObject([$conflict, $conflict])));

        expect(meteredSeries($this->recorder, Metric::Command, meteredCommandLabels(Outcome::Success))->count)->toBe(1)
            ->and(meteredSeries($this->recorder, Metric::Command, meteredCommandLabels(Outcome::Conflict)))->toBeNull();
    });
});

describe('query metrics', function (): void {
    beforeEach(function (): void {
        $this->recorder = new InMemoryMetricsRecorder;
        $this->app->instance(MetricsRecorder::class, $this->recorder);
    });

    it('counts an answered query and a failed one apart', function (): void {
        app(MeteredQueryBus::class)->ask(new MeteredQuery);
        expect(fn () => app(MeteredQueryBus::class)->ask(new MeteredQuery(new RuntimeException('Boom.'))))
            ->toThrow(RuntimeException::class);

        $labels = fn (Outcome $outcome): array => [Label::TYPE => MeteredQuery::class, Label::OUTCOME => $outcome->value];

        expect(meteredSeries($this->recorder, Metric::Query, $labels(Outcome::Success))->count)->toBe(1)
            ->and(meteredSeries($this->recorder, Metric::Query, $labels(Outcome::Failure))->count)->toBe(1);
    });
});

describe('the shared stacks', function (): void {
    beforeEach(function (): void {
        $this->recorder = new InMemoryMetricsRecorder;
        $this->app->instance(MetricsRecorder::class, $this->recorder);
        $this->seed(FrontOfHouseSeeder::class);
    });

    it('measure the commands and the queries of a context', function (): void {
        $this->postJson('/api/front-of-house/tables/table-4/guests', ['guestCount' => 2])->assertOk();
        $this->getJson('/api/front-of-house/floor-plan')->assertOk();

        expect(meteredSeries($this->recorder, Metric::Command, [Label::TYPE => SeatGuests::class, Label::OUTCOME => 'success'])->count)->toBe(1)
            ->and(meteredSeries($this->recorder, Metric::Query, [Label::TYPE => ListFloorPlan::class, Label::OUTCOME => 'success'])->count)->toBe(1);
    });

    it('measure a kitchen command once, retries included', function (): void {
        $stack = fn (string $bus): array => (fn (): array => $this->middleware())->call(app($bus));

        expect($stack(KitchenCommandBus::class))->toContain(MetricsCommandMiddleware::class)
            ->and(array_search(MetricsCommandMiddleware::class, $stack(KitchenCommandBus::class)))
            ->toBeLessThan(array_search(RetryOnConflict::class, $stack(KitchenCommandBus::class)));
    });
});
