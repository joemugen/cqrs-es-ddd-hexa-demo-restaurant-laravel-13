<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus;

use App\Providers\Modules\SharedKernel\Application\Bus\CommandBus;
use App\Providers\Modules\SharedKernel\Application\Bus\CommandMiddleware;
use App\Providers\Modules\SharedKernel\Application\Bus\HandlerMap;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\AuthorizationCommandMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\IdempotencyMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\LoggingCommandMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\TransactionMiddleware;
use Closure;
use Illuminate\Contracts\Container\Container;
use ReflectionClass;

/**
 * The command bus every context builds on. A context extends it, lists its handlers and,
 * when it needs more than the shared stack, overrides middleware().
 */
abstract class MiddlewareCommandBus implements CommandBus
{
    private ?HandlerMap $map = null;

    public function __construct(private readonly Container $container) {}

    public function dispatch(Command $command): ?CommandResponse
    {
        return $this->pipeline()($command);
    }

    public function handlerMap(): HandlerMap
    {
        return $this->map ??= HandlerMap::forCommands($this->handlers());
    }

    /**
     * The handlers of the context this bus lives in, found by ContextDiscovery.
     * A bus overrides it only to list handlers by hand.
     *
     * @return list<class-string>
     */
    protected function handlers(): array
    {
        return $this->container->make(ContextDiscovery::class)->commandHandlers($this->contextRoot());
    }

    /**
     * The bus sits in <context>/Infrastructure/Bus.
     */
    private function contextRoot(): string
    {
        return dirname((string) (new ReflectionClass($this))->getFileName(), 3);
    }

    /**
     * The shared stack, outermost first: log, authorize, whatever the context puts outside the transaction, then the transaction and, inside it, the idempotency of keyed commands.
     *
     * @return list<class-string<CommandMiddleware>>
     */
    protected function middleware(): array
    {
        return [
            LoggingCommandMiddleware::class,
            AuthorizationCommandMiddleware::class,
            ...$this->outsideTransaction(),
            TransactionMiddleware::class,
            IdempotencyMiddleware::class,
        ];
    }

    /**
     * Middleware that must wrap the transaction, such as a retry: each attempt then gets a fresh one.
     *
     * @return list<class-string<CommandMiddleware>>
     */
    protected function outsideTransaction(): array
    {
        return [];
    }

    /**
     * @return Closure(Command): ?CommandResponse
     */
    private function pipeline(): Closure
    {
        $handle = fn (Command $command): ?CommandResponse => $this->container->make($this->handlerMap()->handlerFor($command))($command);

        return array_reduce(
            array_reverse($this->middleware()),
            fn (Closure $next, string $middleware): Closure => fn (Command $command): ?CommandResponse => $this->container->make($middleware)->handle($command, $next),
            $handle,
        );
    }
}
