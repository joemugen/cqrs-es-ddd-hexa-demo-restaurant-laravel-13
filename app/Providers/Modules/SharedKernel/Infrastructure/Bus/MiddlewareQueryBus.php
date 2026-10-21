<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Bus;

use App\Providers\Modules\SharedKernel\Application\Bus\HandlerMap;
use App\Providers\Modules\SharedKernel\Application\Bus\QueryBus;
use App\Providers\Modules\SharedKernel\Application\Bus\QueryMiddleware;
use App\Providers\Modules\SharedKernel\Application\Query;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\AuthorizationQueryMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\LoggingQueryMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\MetricsQueryMiddleware;
use Closure;
use Illuminate\Contracts\Container\Container;

/**
 * The query bus every context builds on. A context extends it, lists its handlers and,
 * when it needs more than the shared stack, overrides middleware().
 */
abstract class MiddlewareQueryBus implements QueryBus
{
    private ?HandlerMap $map = null;

    public function __construct(private readonly Container $container) {}

    public function ask(Query $query): QueryResponse
    {
        return $this->pipeline()($query);
    }

    public function handlerMap(): HandlerMap
    {
        return $this->map ??= HandlerMap::forQueries($this->handlers());
    }

    /**
     * The handlers of the context this bus lives in, found by ContextDiscovery.
     * A bus overrides it only to list handlers by hand.
     *
     * @return list<class-string>
     */
    protected function handlers(): array
    {
        return $this->container->make(ContextDiscovery::class)->queryHandlers($this->context());
    }

    private function context(): string
    {
        return $this->container->make(ContextDiscovery::class)->contextOf(static::class);
    }

    /**
     * The shared stack, outermost first. A context overrides it to add its own.
     *
     * @return list<class-string<QueryMiddleware>>
     */
    protected function middleware(): array
    {
        return [LoggingQueryMiddleware::class, MetricsQueryMiddleware::class, AuthorizationQueryMiddleware::class];
    }

    /**
     * @return Closure(Query): QueryResponse
     */
    private function pipeline(): Closure
    {
        $handle = fn (Query $query): QueryResponse => $this->container->make($this->handlerMap()->handlerFor($query))($query);

        return array_reduce(
            array_reverse($this->middleware()),
            fn (Closure $next, string $middleware): Closure => fn (Query $query): QueryResponse => $this->container->make($middleware)->handle($query, $next),
            $handle,
        );
    }
}
