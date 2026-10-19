<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use App\Providers\Modules\SharedKernel\Infrastructure\Eventing\ConsumerWorker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

/**
 * A consumer that answers from a script: how many events each call handles. Past the script it handles nothing.
 */
final class ScriptedConsumer implements EventConsumer
{
    public int $calls = 0;

    /** @param list<int> $script */
    public function __construct(private array $script, private ?Closure $onCall = null) {}

    public function hasWork(): bool
    {
        return true;
    }

    public function consume(): int
    {
        $this->calls++;

        if ($this->onCall !== null) {
            ($this->onCall)($this);
        }

        return array_shift($this->script) ?? 0;
    }
}

describe('the consumer worker', function (): void {
    beforeEach(function (): void {
        $this->slept = [];
        $this->worker = new ConsumerWorker(
            sleep: function (float $seconds): void {
                $this->slept[] = $seconds;
            },
        );
    });

    it('runs every consumer on each pass and stops once a pass handles nothing', function (): void {
        $first = new ScriptedConsumer([2, 1]);
        $second = new ScriptedConsumer([0, 3]);

        $handled = $this->worker->work([$first, $second], stopWhenEmpty: true);

        expect($handled)->toBe(6)
            ->and($first->calls)->toBe(3)
            ->and($second->calls)->toBe(3);
    });

    it('waits between passes that found nothing, not between busy ones', function (): void {
        $consumer = new ScriptedConsumer([1, 0, 0, 1]);
        $stopAfterFive = new ScriptedConsumer([], function (ScriptedConsumer $self) use ($consumer): void {
            if ($consumer->calls >= 5) {
                $this->worker->stop();
            }
        });

        $this->worker->work([$consumer, $stopAfterFive], sleep: 2.5);

        expect($this->slept)->toBe([2.5, 2.5]);
    });

    it('stops when asked, finishing the pass it is in', function (): void {
        $consumer = new ScriptedConsumer([1, 1, 1, 1], function (ScriptedConsumer $self): void {
            if ($self->calls === 2) {
                $this->worker->stop();
            }
        });

        $handled = $this->worker->work([$consumer]);

        expect($consumer->calls)->toBe(2)
            ->and($handled)->toBe(2);
    });

    it('stops after the maximum time', function (): void {
        $clock = 0.0;
        $worker = new ConsumerWorker(
            sleep: function (float $seconds) use (&$clock): void {
                $clock += $seconds;
            },
            now: function () use (&$clock): float {
                return $clock;
            },
        );
        $consumer = new ScriptedConsumer([]);

        $worker->work([$consumer], sleep: 1.0, maxTime: 3);

        expect($consumer->calls)->toBe(4);
    });

    it('reports a failing consumer and keeps the others going', function (): void {
        Exceptions::fake();
        $failing = new class implements EventConsumer
        {
            public function hasWork(): bool
            {
                return true;
            }

            public function consume(): int
            {
                throw new RuntimeException('poison event');
            }
        };
        $healthy = new ScriptedConsumer([2]);

        $handled = $this->worker->work([$failing, $healthy], stopWhenEmpty: true);

        expect($handled)->toBe(2);
        Exceptions::assertReported(RuntimeException::class);
    });

    it('rolls back what a failing consumer wrote', function (): void {
        Exceptions::fake();
        $failing = new class implements EventConsumer
        {
            public function hasWork(): bool
            {
                return true;
            }

            public function consume(): int
            {
                DB::table(Tables::TABLES)->insert(['id' => 'ghost-table', 'label' => 'Ghost', 'guest_count' => 0]);

                throw new RuntimeException('failed after writing');
            }
        };

        $this->worker->work([$failing], stopWhenEmpty: true);

        expect(DB::table(Tables::TABLES)->where('id', 'ghost-table')->exists())->toBeFalse();
    });

    it('tells what each consumer handled, and only when it handled something', function (): void {
        $told = [];
        $busy = new ScriptedConsumer([2, 0]);
        $idle = new ScriptedConsumer([0, 0]);

        $this->worker->work(
            [$busy, $idle],
            stopWhenEmpty: true,
            onHandled: function (EventConsumer $consumer, int $handled) use (&$told): void {
                $told[] = [$consumer::class, $handled];
            },
        );

        expect($told)->toBe([[ScriptedConsumer::class, 2]]);
    });

    it('tells which consumer failed and why, and still reports the failure', function (): void {
        Exceptions::fake();
        $told = [];
        $failing = new class implements EventConsumer
        {
            public function hasWork(): bool
            {
                return true;
            }

            public function consume(): int
            {
                throw new RuntimeException('poison event');
            }
        };

        $this->worker->work(
            [$failing],
            stopWhenEmpty: true,
            onFailure: function (EventConsumer $consumer, Throwable $failure) use (&$told): void {
                $told[] = $failure->getMessage();
            },
        );

        expect($told)->toBe(['poison event']);
        Exceptions::assertReported(RuntimeException::class);
    });
});
