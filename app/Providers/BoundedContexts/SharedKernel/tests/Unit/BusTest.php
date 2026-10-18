<?php

use App\Providers\BoundedContexts\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\CommandMiddleware;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\Exception\NoHandlerRegistered;
use App\Providers\BoundedContexts\SharedKernel\Application\Bus\QueryMiddleware;
use App\Providers\BoundedContexts\SharedKernel\Application\Command;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandHandler;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandResponse;
use App\Providers\BoundedContexts\SharedKernel\Application\Query;
use App\Providers\BoundedContexts\SharedKernel\Application\QueryHandler;
use App\Providers\BoundedContexts\SharedKernel\Application\QueryResponse;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\MiddlewareCommandBus;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\MiddlewareQueryBus;
use Illuminate\Container\Container;

final readonly class Greet implements Command
{
    public function __construct(public string $name) {}
}

final readonly class Greeting implements CommandResponse
{
    public function __construct(public string $text) {}

    public function jsonSerialize(): array
    {
        return ['text' => $this->text];
    }
}

final readonly class Unhandled implements Command {}

#[AsCommandHandler(Greet::class)]
final readonly class GreetHandler implements CommandHandler
{
    public function __invoke(Greet $command): Greeting
    {
        return new Greeting("Hello {$command->name}");
    }
}

final readonly class Lookup implements Query
{
    public function __construct(public string $key) {}
}

final readonly class Found implements QueryResponse
{
    public function __construct(public string $value) {}

    public function jsonSerialize(): array
    {
        return ['value' => $this->value];
    }
}

#[AsQueryHandler(Lookup::class)]
final readonly class LookupHandler implements QueryHandler
{
    public function __invoke(Lookup $query): Found
    {
        return new Found(strtoupper($query->key));
    }
}

final class Trace
{
    /** @var list<string> */
    public static array $calls = [];
}

final class OuterCommandMiddleware implements CommandMiddleware
{
    public function handle(Command $command, Closure $next): ?CommandResponse
    {
        Trace::$calls[] = 'outer in';
        $response = $next($command);
        Trace::$calls[] = 'outer out';

        return $response;
    }
}

final class InnerCommandMiddleware implements CommandMiddleware
{
    public function handle(Command $command, Closure $next): ?CommandResponse
    {
        Trace::$calls[] = 'inner in';
        $response = $next($command);
        Trace::$calls[] = 'inner out';

        return $response;
    }
}

final class RefusingCommandMiddleware implements CommandMiddleware
{
    public function handle(Command $command, Closure $next): ?CommandResponse
    {
        return null;
    }
}

final class OuterQueryMiddleware implements QueryMiddleware
{
    public function handle(Query $query, Closure $next): QueryResponse
    {
        Trace::$calls[] = 'query outer';

        return $next($query);
    }
}

final class TestingCommandBus extends MiddlewareCommandBus
{
    /** @param list<class-string<CommandMiddleware>> $middleware */
    public function __construct(Container $container, private array $stack = [])
    {
        parent::__construct($container);
    }

    protected function handlers(): array
    {
        return [GreetHandler::class];
    }

    protected function middleware(): array
    {
        return $this->stack;
    }
}

final class TestingQueryBus extends MiddlewareQueryBus
{
    protected function handlers(): array
    {
        return [LookupHandler::class];
    }

    protected function middleware(): array
    {
        return [OuterQueryMiddleware::class];
    }
}

beforeEach(function (): void {
    Trace::$calls = [];
});

describe('the command bus', function (): void {
    it('sends a command to its handler and returns the typed response', function (): void {
        $response = (new TestingCommandBus(new Container))->dispatch(new Greet('Joe'));

        expect($response)->toBeInstanceOf(Greeting::class)
            ->and($response->text)->toBe('Hello Joe');
    });

    it('runs the middleware from the outermost to the innermost, then back out', function (): void {
        $bus = new TestingCommandBus(new Container, [OuterCommandMiddleware::class, InnerCommandMiddleware::class]);

        $bus->dispatch(new Greet('Joe'));

        expect(Trace::$calls)->toBe(['outer in', 'inner in', 'inner out', 'outer out']);
    });

    it('lets a middleware stop the command before it reaches the handler', function (): void {
        $bus = new TestingCommandBus(new Container, [RefusingCommandMiddleware::class, InnerCommandMiddleware::class]);

        expect($bus->dispatch(new Greet('Joe')))->toBeNull()
            ->and(Trace::$calls)->toBe([]);
    });

    it('refuses a command nobody handles', function (): void {
        expect(fn () => (new TestingCommandBus(new Container))->dispatch(new Unhandled))
            ->toThrow(NoHandlerRegistered::class);
    });
});

describe('the query bus', function (): void {
    it('answers a query through its middleware', function (): void {
        $answer = (new TestingQueryBus(new Container))->ask(new Lookup('table-4'));

        expect($answer)->toBeInstanceOf(Found::class)
            ->and($answer->value)->toBe('TABLE-4')
            ->and(Trace::$calls)->toBe(['query outer']);
    });
});
