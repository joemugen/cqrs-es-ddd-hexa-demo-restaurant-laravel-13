<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Eventing;

use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use Illuminate\Support\Facades\Cache;

/**
 * Lets one process at a time run a consumer, wherever it is woken from: a request, a worker or a queued job.
 *
 * The checkpoint is read inside the lock, so a second process that waits its turn starts where the first
 * stopped and no event is handled twice. A process that finds the lock taken skips the pass and returns 0.
 */
final readonly class ExclusiveConsumer implements EventConsumer
{
    public function __construct(private EventConsumer $consumer, private int $lockSeconds) {}

    /**
     * The class of the consumer behind the lock, for logs and worker output.
     */
    public function consumerName(): string
    {
        return $this->consumer::class;
    }

    public function consume(): int
    {
        $handled = Cache::lock('event-consumer:'.$this->consumer::class, $this->lockSeconds)
            ->get(fn (): int => $this->consumer->consume());

        return $handled === false ? 0 : $handled;
    }
}
