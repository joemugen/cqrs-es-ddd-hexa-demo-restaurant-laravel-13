<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus;

use App\Providers\BoundedContexts\SharedKernel\Application\Bus\CommandBus;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\CommandMiddleware;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\HandlerMap;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandResponse;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\Middleware\TransactionMiddleware;
use Closure;
use Illuminate\Contracts\Container\Container;

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
     * @return list<class-string>
     */
    abstract protected function handlers(): array;

    /**
     * The shared stack, outermost first. A context overrides it to add its own.
     *
     * @return list<class-string<CommandMiddleware>>
     */
    protected function middleware(): array
    {
        return [TransactionMiddleware::class];
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
