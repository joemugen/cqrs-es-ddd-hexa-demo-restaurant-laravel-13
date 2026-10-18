<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Infrastructure\Console;

use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Eventing\ConsumerWorker;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use ReflectionClass;

/**
 * Subscribes to the event log for one context: consumes what is new, then waits for more until it is stopped.
 * A context extends it and only names itself; its consumers are discovered in Infrastructure/Processors
 * and Infrastructure/Projection. Run one worker per context, or two will consume the same events.
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

    public function handle(ContextDiscovery $discovery, ConsumerWorker $worker, Container $container): int
    {
        $consumers = array_map(
            fn (string $consumer) => $container->make($consumer),
            $discovery->eventConsumers($this->contextRoot()),
        );

        if ($consumers === []) {
            $this->components->warn('The '.$this->contextName().' context has no event consumer.');

            return self::SUCCESS;
        }

        if (function_exists('pcntl_signal')) {
            $this->trap([SIGTERM, SIGINT], fn () => $worker->stop());
        }

        $this->components->info('Consuming events for '.$this->contextName().' ('.count($consumers).' consumers). Stop with Ctrl+C.');

        $handled = $worker->work(
            $consumers,
            sleep: (float) $this->option('sleep'),
            stopWhenEmpty: (bool) $this->option('stop-when-empty'),
            maxTime: (int) $this->option('max-time'),
        );

        $this->components->info("Consumed {$handled} event(s).");

        return self::SUCCESS;
    }

    /**
     * The command sits in <context>/Infrastructure/Console.
     */
    private function contextRoot(): string
    {
        return dirname((string) (new ReflectionClass($this))->getFileName(), 3);
    }
}
