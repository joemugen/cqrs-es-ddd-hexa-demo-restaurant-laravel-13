<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus;

use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;
use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use ReflectionClass;

/**
 * Finds what a context wires by convention: its handlers, so no bus lists them by hand, and its event consumers.
 *
 * Command handlers live in Application/Command and query handlers in Infrastructure/Query. A class there is a handler
 * when it implements the contract or carries the attribute, so a handler that forgets one is reported by the HandlerMap
 * instead of being silently ignored. `php artisan contexts:cache` writes the result to a file that `optimize` refreshes.
 */
final class ContextDiscovery
{
    /** @var array<string, array{commands: list<class-string>, queries: list<class-string>, consumers: list<class-string>}>|null */
    private ?array $cached = null;

    public function __construct(private readonly string $cachePath) {}

    /**
     * @return list<class-string>
     */
    public function commandHandlers(string $contextRoot): array
    {
        return $this->cachedContext($contextRoot)['commands'] ?? $this->scan($contextRoot.'/Application/Command', CommandHandler::class, AsCommandHandler::class);
    }

    /**
     * @return list<class-string>
     */
    public function queryHandlers(string $contextRoot): array
    {
        return $this->cachedContext($contextRoot)['queries'] ?? $this->scan($contextRoot.'/Infrastructure/Query', QueryHandler::class, AsQueryHandler::class);
    }

    /**
     * The processors and projectors of a context, in a stable order.
     *
     * @return list<class-string<EventConsumer>>
     */
    public function eventConsumers(string $contextRoot): array
    {
        return $this->cachedContext($contextRoot)['consumers'] ?? $this->scanConsumers($contextRoot);
    }

    /**
     * Scans every context under $contextsRoot and writes the result to the cache file.
     */
    public function cache(string $contextsRoot): string
    {
        $contexts = [];

        foreach (glob($contextsRoot.'/*', GLOB_ONLYDIR) ?: [] as $contextRoot) {
            $contexts[$this->key($contextRoot)] = [
                'commands' => $this->scan($contextRoot.'/Application/Command', CommandHandler::class, AsCommandHandler::class),
                'queries' => $this->scan($contextRoot.'/Infrastructure/Query', QueryHandler::class, AsQueryHandler::class),
                'consumers' => $this->scanConsumers($contextRoot),
            ];
        }

        ksort($contexts);
        file_put_contents($this->cachePath, '<?php return '.var_export($contexts, true).';'.PHP_EOL);
        $this->cached = $contexts;

        return $this->cachePath;
    }

    public function clear(): void
    {
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }

        $this->cached = null;
    }

    public function cachePath(): string
    {
        return $this->cachePath;
    }

    /**
     * @return array{commands: list<class-string>, queries: list<class-string>, consumers: list<class-string>}|null
     */
    private function cachedContext(string $contextRoot): ?array
    {
        if ($this->cached === null) {
            $this->cached = file_exists($this->cachePath) ? require $this->cachePath : [];
        }

        return $this->cached[$this->key($contextRoot)] ?? null;
    }

    /**
     * @param  class-string  $contract
     * @param  class-string|null  $attribute
     * @return list<class-string>
     */
    private function scan(string $directory, string $contract, ?string $attribute = null): array
    {
        $classes = [];

        foreach (glob($directory.'/*.php') ?: [] as $file) {
            $class = $this->classOf($file);

            if (
                class_exists($class)
                && (is_subclass_of($class, $contract) || ($attribute !== null && (new ReflectionClass($class))->getAttributes($attribute) !== []))
            ) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<class-string<EventConsumer>>
     */
    private function scanConsumers(string $contextRoot): array
    {
        return [
            ...$this->scan($contextRoot.'/Infrastructure/Processors', EventConsumer::class),
            ...$this->scan($contextRoot.'/Infrastructure/Projection', EventConsumer::class),
        ];
    }

    /**
     * PSR-4: app/Providers/Foo/Bar.php is App\Providers\Foo\Bar.
     *
     * @return class-string
     */
    private function classOf(string $file): string
    {
        $relative = substr($file, strlen(app_path()) + 1);

        return 'App\\'.str_replace(['/', '.php'], ['\\', ''], $relative);
    }

    private function key(string $contextRoot): string
    {
        return ltrim(substr($contextRoot, strlen(base_path())), '/');
    }
}
