<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus;

use App\Providers\BoundedContexts\SharedKernel\Application\Bus\HandlerMap;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\QueryBus;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\QueryMiddleware;
use App\Providers\BoundedContexts\SharedKernel\Application\Query;
use App\Providers\BoundedContexts\SharedKernel\Application\QueryResponse;
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
     * @return list<class-string>
     */
    abstract protected function handlers(): array;

    /**
     * Outermost first.
     *
     * @return list<class-string<QueryMiddleware>>
     */
    protected function middleware(): array
    {
        return [];
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
