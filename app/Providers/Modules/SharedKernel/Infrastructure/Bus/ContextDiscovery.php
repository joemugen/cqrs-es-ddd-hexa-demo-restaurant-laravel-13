<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus;

use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;
use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use ReflectionClass;

/**
 * Finds what a context wires by convention: its handlers, so no bus lists them by hand, its event consumers and the
 * contracts it publishes. A context is a namespace, and what it holds sits in sub-namespaces named by the configuration
 * (`config/contexts.php`): no path is spelled here.
 *
 * A class in the namespace of the commands (or queries) is a handler
 * when it implements the contract or carries the attribute, so a handler that forgets one is reported by the HandlerMap
 * instead of being silently ignored. `php artisan contexts:cache` writes the result to a file that `optimize` refreshes.
 */
final class ContextDiscovery
{
    /** @var array<string, array{commands: list<class-string>, queries: list<class-string>, consumers: list<class-string>, contracts: list<class-string>, consumed: list<class-string>}>|null */
    private ?array $cached = null;

    /**
     * @param  array{namespace: string, layout: array{commands: string, queries: string, processors: string, projectors: string, contracts: string, consumed: string}}  $configuration
     */
    public function __construct(
        private readonly string $cachePath,
        private readonly array $configuration,
        private readonly ComposerNamespaces $namespaces = new ComposerNamespaces,
    ) {}

    /**
     * The bounded contexts, as namespaces.
     *
     * @return list<string>
     */
    public function contexts(): array
    {
        $contexts = $this->namespaces->namespacesUnder($this->configuration['namespace']);
        sort($contexts);

        return $contexts;
    }

    /**
     * The context a class belongs to: the namespace under the contexts one, whatever the layer.
     */
    public function contextOf(string $class): string
    {
        $prefix = $this->configuration['namespace'].'\\';

        return $this->configuration['namespace'].'\\'.explode('\\', substr($class, strlen($prefix)))[0];
    }

    /**
     * @return list<class-string>
     */
    public function commandHandlers(string $context): array
    {
        return $this->cachedContext($context)['commands'] ?? $this->scanCommands($context);
    }

    /**
     * @return list<class-string>
     */
    public function queryHandlers(string $context): array
    {
        return $this->cachedContext($context)['queries'] ?? $this->scanQueries($context);
    }

    /**
     * The processors and projectors of a context, in a stable order.
     *
     * @return list<class-string<EventConsumer>>
     */
    public function eventConsumers(string $context): array
    {
        return $this->cachedContext($context)['consumers'] ?? $this->scanConsumers($context);
    }

    /**
     * The contracts a context publishes to the others.
     *
     * @return list<class-string<IntegrationContract>>
     */
    public function contracts(string $context): array
    {
        return $this->cachedContext($context)['contracts'] ?? $this->scanContracts($context);
    }

    /**
     * What a context reads of the contracts of the others.
     *
     * @return list<class-string<ConsumedMessage>>
     */
    public function consumedMessages(string $context): array
    {
        return $this->cachedContext($context)['consumed'] ?? $this->scanConsumedMessages($context);
    }

    /**
     * Scans every context and writes the result to the cache file.
     */
    public function cache(): string
    {
        $contexts = [];

        foreach ($this->contexts() as $context) {
            $contexts[$context] = [
                'commands' => $this->scanCommands($context),
                'queries' => $this->scanQueries($context),
                'consumers' => $this->scanConsumers($context),
                'contracts' => $this->scanContracts($context),
                'consumed' => $this->scanConsumedMessages($context),
            ];
        }

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
     * @return array{commands: list<class-string>, queries: list<class-string>, consumers: list<class-string>, contracts: list<class-string>, consumed: list<class-string>}|null
     */
    private function cachedContext(string $context): ?array
    {
        if ($this->cached === null) {
            $this->cached = file_exists($this->cachePath) ? require $this->cachePath : [];
        }

        return $this->cached[$context] ?? null;
    }

    /**
     * @return list<class-string>
     */
    private function scanCommands(string $context): array
    {
        return $this->scan($context, 'commands', CommandHandler::class, AsCommandHandler::class);
    }

    /**
     * @return list<class-string>
     */
    private function scanQueries(string $context): array
    {
        return $this->scan($context, 'queries', QueryHandler::class, AsQueryHandler::class);
    }

    /**
     * @return list<class-string<IntegrationContract>>
     */
    private function scanContracts(string $context): array
    {
        return $this->scan($context, 'contracts', IntegrationContract::class);
    }

    /**
     * @return list<class-string<ConsumedMessage>>
     */
    private function scanConsumedMessages(string $context): array
    {
        return array_values(array_filter(
            $this->scan($context, 'consumed', ConsumedMessage::class),
            fn (string $class): bool => ! (new ReflectionClass($class))->isAbstract(),
        ));
    }

    /**
     * @return list<class-string<EventConsumer>>
     */
    private function scanConsumers(string $context): array
    {
        return [
            ...$this->scan($context, 'processors', EventConsumer::class),
            ...$this->scan($context, 'projectors', EventConsumer::class),
        ];
    }

    /**
     * @param  class-string  $contract
     * @param  class-string|null  $attribute
     * @return list<class-string>
     */
    private function scan(string $context, string $layer, string $contract, ?string $attribute = null): array
    {
        $classes = array_values(array_filter(
            $this->namespaces->classesIn($context.'\\'.$this->configuration['layout'][$layer]),
            fn (string $class): bool => is_subclass_of($class, $contract)
                || ($attribute !== null && (new ReflectionClass($class))->getAttributes($attribute) !== []),
        ));
        sort($classes);

        return $classes;
    }
}
