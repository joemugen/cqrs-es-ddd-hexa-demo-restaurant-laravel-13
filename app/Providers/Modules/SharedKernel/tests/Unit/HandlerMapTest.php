<?php

use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\Modules\SharedKernel\Application\Bus\Exception\HandlerMismatch;
use App\Providers\Modules\SharedKernel\Application\Bus\Exception\NoHandlerRegistered;
use App\Providers\Modules\SharedKernel\Application\Bus\HandlerMap;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use App\Providers\Modules\SharedKernel\Application\Query;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;

final readonly class Ping implements Command {}
final readonly class Pong implements CommandResponse
{
    public function jsonSerialize(): array
    {
        return ['pong' => true];
    }
}
final readonly class Other implements Command {}
final readonly class Ask implements Query {}
final readonly class Answer implements QueryResponse
{
    public function jsonSerialize(): array
    {
        return [];
    }
}

#[AsCommandHandler(Ping::class)]
final readonly class PingHandler implements CommandHandler
{
    public function __invoke(Ping $command): Pong
    {
        return new Pong;
    }
}

#[AsCommandHandler(Ping::class)]
final readonly class NothingToSayHandler implements CommandHandler
{
    public function __invoke(Ping $command): ?Pong
    {
        return null;
    }
}

final readonly class UndeclaredHandler implements CommandHandler
{
    public function __invoke(Ping $command): Pong
    {
        return new Pong;
    }
}

#[AsCommandHandler(Ping::class)]
final readonly class WrongParameterHandler implements CommandHandler
{
    public function __invoke(Other $command): Pong
    {
        return new Pong;
    }
}

#[AsCommandHandler(Ping::class)]
final readonly class NotInvokableHandler implements CommandHandler
{
    public function handle(Ping $command): Pong
    {
        return new Pong;
    }
}

#[AsCommandHandler(Ping::class)]
final readonly class WrongResponseHandler implements CommandHandler
{
    public function __invoke(Ping $command): array
    {
        return [];
    }
}

#[AsCommandHandler(Ping::class)]
final readonly class MissingContractHandler
{
    public function __invoke(Ping $command): Pong
    {
        return new Pong;
    }
}

#[AsCommandHandler(Ask::class)]
final readonly class AskedWithACommandAttributeHandler implements CommandHandler
{
    public function __invoke(Ask $command): Pong
    {
        return new Pong;
    }
}

#[AsQueryHandler(Ask::class)]
final readonly class AskHandler implements QueryHandler
{
    public function __invoke(Ask $query): Answer
    {
        return new Answer;
    }
}

#[AsQueryHandler(Ask::class)]
final readonly class NullableAnswerHandler implements QueryHandler
{
    public function __invoke(Ask $query): ?Answer
    {
        return null;
    }
}

describe('the handler map', function (): void {
    describe('for commands', function (): void {
        it('finds the handler of a command', function (): void {
            $map = HandlerMap::forCommands([PingHandler::class]);

            expect($map->handlerFor(new Ping))->toBe(PingHandler::class)
                ->and($map->handlers())->toBe([Ping::class => PingHandler::class]);
        });

        it('accepts a handler that may have nothing to answer', function (): void {
            expect(HandlerMap::forCommands([NothingToSayHandler::class])->handlerFor(new Ping))
                ->toBe(NothingToSayHandler::class);
        });

        it('refuses a command nobody handles', function (): void {
            expect(fn () => HandlerMap::forCommands([PingHandler::class])->handlerFor(new Other))
                ->toThrow(NoHandlerRegistered::class, 'Other');
        });

        it('refuses a handler that does not declare the command it handles', function (): void {
            expect(fn () => HandlerMap::forCommands([UndeclaredHandler::class]))
                ->toThrow(HandlerMismatch::class, 'must declare #[AsCommandHandler]');
        });

        it('refuses a handler whose parameter is not the declared command', function (): void {
            expect(fn () => HandlerMap::forCommands([WrongParameterHandler::class]))
                ->toThrow(HandlerMismatch::class, '__invoke() must accept Ping');
        });

        it('refuses a handler that cannot be invoked', function (): void {
            expect(fn () => HandlerMap::forCommands([NotInvokableHandler::class]))
                ->toThrow(HandlerMismatch::class, 'must have a public __invoke()');
        });

        it('refuses a handler that does not answer with a CommandResponse', function (): void {
            expect(fn () => HandlerMap::forCommands([WrongResponseHandler::class]))
                ->toThrow(HandlerMismatch::class, 'must return a CommandResponse');
        });

        it('refuses a class that is not a CommandHandler', function (): void {
            expect(fn () => HandlerMap::forCommands([MissingContractHandler::class]))
                ->toThrow(HandlerMismatch::class, 'must implement CommandHandler');
        });

        it('refuses a declared message that is not a command', function (): void {
            expect(fn () => HandlerMap::forCommands([AskedWithACommandAttributeHandler::class]))
                ->toThrow(HandlerMismatch::class, 'must implement Command');
        });

        it('refuses two handlers for the same command', function (): void {
            expect(fn () => HandlerMap::forCommands([PingHandler::class, NothingToSayHandler::class]))
                ->toThrow(HandlerMismatch::class, 'already handled by');
        });
    });

    describe('for queries', function (): void {
        it('finds the handler of a query', function (): void {
            expect(HandlerMap::forQueries([AskHandler::class])->handlerFor(new Ask))->toBe(AskHandler::class);
        });

        it('refuses a handler that may answer nothing', function (): void {
            expect(fn () => HandlerMap::forQueries([NullableAnswerHandler::class]))
                ->toThrow(HandlerMismatch::class, 'must return a QueryResponse');
        });

        it('refuses a command handler', function (): void {
            expect(fn () => HandlerMap::forQueries([PingHandler::class]))
                ->toThrow(HandlerMismatch::class, 'must declare #[AsQueryHandler]');
        });
    });
});
