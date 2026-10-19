<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Console;

use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use App\Providers\Modules\SharedKernel\Infrastructure\Eventing\ConsumerWorker;
use App\Providers\Modules\SharedKernel\Infrastructure\Eventing\EventConsumers;
use App\Providers\Modules\SharedKernel\Infrastructure\Eventing\ExclusiveConsumer;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\NullStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Throwable;

/**
 * Subscribes to the event log for one context: consumes what is new, then waits for more until it is stopped.
 * A context extends it and only names itself; its consumers are discovered in Infrastructure/Processors
 * and Infrastructure/Projection. A lock keeps a second worker for the same context from starting, and each consumer
 * has its own lock, so even then no event is handled twice.
 */
abstract class ConsumeEvents extends Command
{
    public function __construct()
    {
        $this->signature = $this->contextName().':consume'
            .' {--sleep=1 : Seconds to wait when there is nothing left to consume}'
            .' {--stop-when-empty : Stop as soon as there is nothing left to consume}'
            .' {--max-time=0 : Stop after this many seconds, 0 to keep going}';
        $this->description = 'Consume the events the '.$this->contextName().' context reacts to, then wait for more';

        parent::__construct();
    }

    /**
     * The command prefix, such as kitchen.
     */
    abstract protected function contextName(): string;

    public function handle(ContextDiscovery $discovery, ConsumerWorker $worker, EventConsumers $factory): int
    {
        $consumers = array_map(
            fn (string $consumer) => $factory->make($consumer),
            $discovery->eventConsumers($this->contextRoot()),
        );

        if ($consumers === []) {
            $this->components->warn('The '.$this->contextName().' context has no event consumer.');

            return self::SUCCESS;
        }

        $sleep = (float) $this->option('sleep');
        $lock = Cache::lock('event-worker:'.$this->contextName(), max((int) config('consumption.lock_seconds'), (int) ceil($sleep) * 2));

        if (! $lock->get()) {
            $this->components->error('Another '.$this->contextName().' worker is already running.');

            return self::FAILURE;
        }

        if (Cache::getStore() instanceof ArrayStore || Cache::getStore() instanceof NullStore) {
            $this->components->warn('The cache store keeps locks inside this process only. Use database or redis so a second worker is refused.');
        }

        if (function_exists('pcntl_signal')) {
            $this->trap([SIGTERM, SIGINT], fn () => $worker->stop());
        }

        $this->components->info('Consuming events for '.$this->contextName().' ('.count($consumers).' consumers). Stop with Ctrl+C.');

        try {
            $handled = $worker->work(
                $consumers,
                sleep: $sleep,
                stopWhenEmpty: (bool) $this->option('stop-when-empty'),
                maxTime: (int) $this->option('max-time'),
                afterPass: fn () => $lock->refresh(),
                onHandled: fn (EventConsumer $consumer, int $handled) => $this->components->twoColumnDetail(
                    now()->format('H:i:s').' '.$this->nameOf($consumer),
                    "{$handled} event(s)",
                ),
                onFailure: fn (EventConsumer $consumer, Throwable $failure) => $this->components->error(
                    $this->nameOf($consumer).' failed and will be retried: '.$failure->getMessage(),
                ),
            );
        } finally {
            $lock->release();
        }

        $this->components->info("Consumed {$handled} event(s).");

        return self::SUCCESS;
    }

    private function nameOf(EventConsumer $consumer): string
    {
        return class_basename($consumer instanceof ExclusiveConsumer ? $consumer->consumerName() : $consumer::class);
    }

    /**
     * The command sits in <context>/Infrastructure/Console.
     */
    private function contextRoot(): string
    {
        return dirname((string) (new ReflectionClass($this))->getFileName(), 3);
    }
}
