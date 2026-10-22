<?php

use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvent;
use App\Providers\Modules\SharedKernel\Domain\RecordedEvents;
use App\Providers\Modules\SharedKernel\Domain\StreamId;
use App\Providers\Modules\SharedKernel\Infrastructure\Eventing\ConsumerWorker;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * @return list<string> the statements of a callback that write, and the transactions it opens
 */
function writesDuring(Closure $work): array
{
    $writes = [];

    DB::listen(function ($query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete|replace)/i', $query->sql) === 1) {
            $writes[] = $query->sql;
        }
    });
    Event::listen(TransactionBeginning::class, function () use (&$writes): void {
        $writes[] = 'BEGIN';
    });

    $work();

    return $writes;
}

function appendIrrelevantEvent(): void
{
    app(EventStore::class)->append(
        new StreamId('integration-'.Str::uuid()),
        0,
        RecordedEvents::of(RecordedEvent::now('tests.noise', [])),
    );
}

describe('an idle worker', function (): void {
    describe('every consumer', function (): void {
        it('has no work on an empty log', function (string $consumer): void {
            expect(app($consumer)->hasWork())->toBeFalse();
        })->with([
            ...classesIn('{FrontOfHouse,Kitchen,Billing}/Infrastructure/Processors/*.php'),
            ...classesIn('{FrontOfHouse,Kitchen,Billing}/Infrastructure/Projection/*Projector.php'),
        ]);

        it('has work once an event is past its checkpoint, and none again once it consumed it', function (string $consumer): void {
            config(['consumption.mode' => 'worker']);
            appendIrrelevantEvent();
            $instance = app($consumer);

            expect($instance->hasWork())->toBeTrue();

            $instance->consume();

            expect($instance->hasWork())->toBeFalse();
        })->with([
            ...classesIn('{FrontOfHouse,Kitchen,Billing}/Infrastructure/Processors/*.php'),
            ...classesIn('{FrontOfHouse,Kitchen,Billing}/Infrastructure/Projection/*Projector.php'),
        ]);
    });

    describe('a pass over consumers with nothing to do', function (): void {
        it('writes nothing and opens no transaction, so it never contends with the API', function (): void {
            $worker = new ConsumerWorker(sleep: fn (float $seconds) => null);
            $consumers = array_map(
                fn (string $class) => app($class),
                classesIn('{FrontOfHouse,Kitchen,Billing}/Infrastructure/Processors/*.php'),
            );

            $writes = writesDuring(fn () => $worker->work($consumers, stopWhenEmpty: true));

            expect($writes)->toBe([]);
        });

        it('does not call a consumer that has nothing to do', function (): void {
            $idle = new class implements EventConsumer
            {
                public int $consumed = 0;

                public function hasWork(): bool
                {
                    return false;
                }

                public function consume(): int
                {
                    $this->consumed++;

                    return 0;
                }
            };

            (new ConsumerWorker(sleep: fn (float $seconds) => null))->work([$idle], stopWhenEmpty: true);

            expect($idle->consumed)->toBe(0);
        });
    });

    describe('the worker lock', function (): void {
        it('is renewed now and then, not on every pass', function (): void {
            $this->artisan('kitchen:consume', ['--stop-when-empty' => true])->assertSuccessful();

            $writes = writesDuring(function (): void {
                $this->artisan('kitchen:consume', ['--stop-when-empty' => true])->assertSuccessful();
            });

            expect(count(array_filter($writes, fn (string $sql): bool => str_contains($sql, 'cache_locks'))))->toBeLessThanOrEqual(2);
        });
    });
});
