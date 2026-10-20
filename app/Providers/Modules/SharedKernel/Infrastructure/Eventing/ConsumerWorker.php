<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Eventing;

use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use App\Providers\Modules\SharedKernel\Infrastructure\Causation;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs event consumers in a loop and waits when there is nothing to consume.
 *
 * Each consumer runs in its own transaction, as it does in sync mode: one failure is reported,
 * rolled back and does not stop the others. A consumer that keeps failing leaves its checkpoint where it was,
 * so the worker retries it on the next pass after the idle wait.
 */
final class ConsumerWorker
{
    private bool $stop = false;

    /**
     * @param  Closure(float): void|null  $sleep
     * @param  Closure(): float|null  $now  seconds, a monotonic clock
     */
    public function __construct(private readonly ?Closure $sleep = null, private readonly ?Closure $now = null) {}

    /**
     * Ask the loop to end after the pass it is in.
     */
    public function stop(): void
    {
        $this->stop = true;
    }

    /**
     * @param  list<EventConsumer>  $consumers
     * @param  float  $sleep  seconds to wait after a pass that handled nothing
     * @param  int  $maxTime  seconds after which the worker stops, 0 for never
     * @param  Closure(): void|null  $afterPass  called after every pass, to renew a lock
     * @param  Closure(EventConsumer, int): void|null  $onHandled  called for a consumer that handled events
     * @param  Closure(EventConsumer, Throwable): void|null  $onFailure  called after a failure was reported and rolled back
     * @return int the number of events handled
     */
    public function work(
        array $consumers,
        float $sleep = 1.0,
        bool $stopWhenEmpty = false,
        int $maxTime = 0,
        ?Closure $afterPass = null,
        ?Closure $onHandled = null,
        ?Closure $onFailure = null,
    ): int {
        $this->stop = false;
        $handledInTotal = 0;
        $startedAt = $this->clock();

        while (! $this->stop) {
            $handled = 0;

            foreach ($consumers as $consumer) {
                $handled += $this->consumeIsolated($consumer, $onHandled, $onFailure);
            }

            $handledInTotal += $handled;

            if ($afterPass !== null) {
                $afterPass();
            }

            if ($handled === 0 && $stopWhenEmpty) {
                break;
            }

            if ($maxTime > 0 && $this->clock() - $startedAt >= $maxTime) {
                break;
            }

            if ($handled === 0 && ! $this->stop) {
                $this->wait($sleep);
            }
        }

        return $handledInTotal;
    }

    private function consumeIsolated(EventConsumer $consumer, ?Closure $onHandled, ?Closure $onFailure): int
    {
        try {
            if (! $consumer->hasWork()) {
                return 0;
            }

            $handled = Correlation::within(fn (): int => Causation::within(fn (): int => DB::transaction(fn (): int => $consumer->consume())));
        } catch (Throwable $failure) {
            report($failure);

            if ($onFailure !== null) {
                $onFailure($consumer, $failure);
            }

            return 0;
        }

        if ($handled > 0 && $onHandled !== null) {
            $onHandled($consumer, $handled);
        }

        return $handled;
    }

    private function wait(float $seconds): void
    {
        $this->sleep !== null ? ($this->sleep)($seconds) : usleep((int) ($seconds * 1_000_000));
    }

    private function clock(): float
    {
        return $this->now !== null ? ($this->now)() : hrtime(true) / 1_000_000_000;
    }
}
