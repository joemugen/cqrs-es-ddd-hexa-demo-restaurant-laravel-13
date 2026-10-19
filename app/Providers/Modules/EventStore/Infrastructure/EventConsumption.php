<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Infrastructure;

use App\Providers\Modules\EventStore\Application\AfterAppend;
use App\Providers\Modules\SharedKernel\Application\Consumption\RunsOnQueue;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use App\Providers\Modules\SharedKernel\Infrastructure\Correlation;
use Illuminate\Contracts\Container\Container;
use ReflectionClass;

/**
 * Wakes the event consumers of a context when a fact is appended, unless the deployment runs workers instead.
 *
 * The mode is read when the event fires, so it can change without rebooting: `sync` consumes here, `worker` leaves
 * the log to `php artisan <context>:consume`. A consumer marked #[RunsOnQueue] dispatches its job in sync mode.
 */
final readonly class EventConsumption
{
    public function __construct(
        private ContextDiscovery $discovery,
        private AfterAppend $afterAppend,
        private Container $container,
    ) {}

    public function subscribe(string $contextRoot): void
    {
        foreach ($this->discovery->eventConsumers($contextRoot) as $consumer) {
            $this->afterAppend->listen(function () use ($consumer): void {
                if (config('consumption.mode') !== 'sync') {
                    return;
                }

                $this->wake($consumer);
            });
        }
    }

    /**
     * @param  class-string  $consumer
     */
    private function wake(string $consumer): void
    {
        $onQueue = (new ReflectionClass($consumer))->getAttributes(RunsOnQueue::class);

        if ($onQueue !== []) {
            $onQueue[0]->newInstance()->job::dispatch();

            return;
        }

        Correlation::within(fn (): int => $this->container->make($consumer)->consume());
    }
}
