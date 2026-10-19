<?php

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Tables;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsCommandHandler;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\Authorize;
use App\Providers\Modules\SharedKernel\Application\Command;
use App\Providers\Modules\SharedKernel\Application\CommandHandler;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;
use App\Providers\Modules\SharedKernel\Application\Query;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use App\Providers\Modules\SharedKernel\Application\QueryResponse;
use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\AuthorizationCommandMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\AuthorizationQueryMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\LoggingCommandMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\LoggingQueryMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\Middleware\TransactionMiddleware;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\MiddlewareCommandBus;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\MiddlewareQueryBus;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class WriteThenFail implements Command {}

final readonly class WroteNothing implements CommandResponse
{
    public function jsonSerialize(): array
    {
        return [];
    }
}

#[AsCommandHandler(WriteThenFail::class)]
final readonly class WriteThenFailHandler implements CommandHandler
{
    public function __invoke(WriteThenFail $command): WroteNothing
    {
        DB::table(Tables::TABLES)->insert(['id' => 'ghost-table', 'label' => 'Ghost', 'guest_count' => 0]);

        throw new RuntimeException('The handler failed after writing.');
    }
}

#[Authorize('serve-the-pass')]
final readonly class ServeThePass implements Command {}

#[AsCommandHandler(ServeThePass::class)]
final readonly class ServeThePassHandler implements CommandHandler
{
    public function __invoke(ServeThePass $command): WroteNothing
    {
        return new WroteNothing;
    }
}

#[Authorize('read-the-pass')]
final readonly class ReadThePass implements Query {}

final readonly class PassContents implements QueryResponse
{
    public function jsonSerialize(): array
    {
        return [];
    }
}

#[AsQueryHandler(ReadThePass::class)]
final readonly class ReadThePassHandler implements QueryHandler
{
    public function __invoke(ReadThePass $query): PassContents
    {
        return new PassContents;
    }
}

final readonly class OpenToAll implements Command {}

#[AsCommandHandler(OpenToAll::class)]
final readonly class OpenToAllHandler implements CommandHandler
{
    public function __invoke(OpenToAll $command): WroteNothing
    {
        return new WroteNothing;
    }
}

final class UnguardedTestBus extends MiddlewareCommandBus
{
    protected function handlers(): array
    {
        return [OpenToAllHandler::class];
    }

    protected function middleware(): array
    {
        return [AuthorizationCommandMiddleware::class];
    }
}

final class FailingLoggedTestBus extends MiddlewareCommandBus
{
    protected function handlers(): array
    {
        return [WriteThenFailHandler::class];
    }

    protected function middleware(): array
    {
        return [LoggingCommandMiddleware::class, TransactionMiddleware::class];
    }
}

final class RefusingLoggedTestBus extends MiddlewareCommandBus
{
    protected function handlers(): array
    {
        return [ServeThePassHandler::class];
    }

    protected function middleware(): array
    {
        return [LoggingCommandMiddleware::class, AuthorizationCommandMiddleware::class];
    }
}

final class TransactionalTestBus extends MiddlewareCommandBus
{
    protected function handlers(): array
    {
        return [WriteThenFailHandler::class];
    }

    protected function middleware(): array
    {
        return [TransactionMiddleware::class];
    }
}

final class LoggedTestBus extends MiddlewareCommandBus
{
    protected function handlers(): array
    {
        return [ServeThePassHandler::class];
    }

    protected function middleware(): array
    {
        return [LoggingCommandMiddleware::class];
    }
}

final class AuthorizedTestBus extends MiddlewareCommandBus
{
    protected function handlers(): array
    {
        return [ServeThePassHandler::class];
    }

    protected function middleware(): array
    {
        return [AuthorizationCommandMiddleware::class];
    }
}

final class AuthorizedTestQueryBus extends MiddlewareQueryBus
{
    protected function handlers(): array
    {
        return [ReadThePassHandler::class];
    }

    protected function middleware(): array
    {
        return [AuthorizationQueryMiddleware::class];
    }
}

final class LoggedTestQueryBus extends MiddlewareQueryBus
{
    protected function handlers(): array
    {
        return [ReadThePassHandler::class];
    }

    protected function middleware(): array
    {
        return [LoggingQueryMiddleware::class];
    }
}

describe('bus middleware', function (): void {
    describe('transaction', function (): void {
        it('rolls back what a handler wrote when it fails', function (): void {
            expect(fn () => app(TransactionalTestBus::class)->dispatch(new WriteThenFail))
                ->toThrow(RuntimeException::class);

            expect(DB::table(Tables::TABLES)->where('id', 'ghost-table')->exists())->toBeFalse();
        });
    });

    describe('logging', function (): void {
        it('logs a handled command with its duration and a correlation id', function (): void {
            Log::spy();

            app(LoggedTestBus::class)->dispatch(new ServeThePass);

            Log::shouldHaveReceived('info')->withArgs(
                fn (string $message, array $context): bool => $message === 'Command handled'
                    && $context['command'] === ServeThePass::class
                    && is_float($context['duration_ms']),
            )->once();
            expect(Context::get('correlation_id'))->toBeString();
        });

        it('keeps the correlation id of the request across commands', function (): void {
            Log::spy();
            Context::add('correlation_id', 'req-42');

            app(LoggedTestBus::class)->dispatch(new ServeThePass);

            expect(Context::get('correlation_id'))->toBe('req-42');
        });

        it('logs a handled query', function (): void {
            Log::spy();

            app(LoggedTestQueryBus::class)->ask(new ReadThePass);

            Log::shouldHaveReceived('info')->withArgs(
                fn (string $message, array $context): bool => $message === 'Query answered'
                    && $context['query'] === ReadThePass::class,
            )->once();
        });

        it('logs a crash as a warning and lets it through', function (): void {
            Log::spy();

            expect(fn () => app(FailingLoggedTestBus::class)->dispatch(new WriteThenFail))->toThrow(RuntimeException::class);

            Log::shouldHaveReceived('warning')->withArgs(
                fn (string $message, array $context): bool => $message === 'Command failed'
                    && $context['exception'] === RuntimeException::class,
            )->once();
        });

        it('logs a refusal as information with its status and lets it through', function (): void {
            Log::spy();

            expect(fn () => app(RefusingLoggedTestBus::class)->dispatch(new ServeThePass))->toThrow(DomainException::class);

            Log::shouldHaveReceived('info')->withArgs(
                fn (string $message, array $context): bool => $message === 'Command refused' && $context['status'] === 403,
            )->once();
        });
    });

    describe('authorization', function (): void {
        beforeEach(function (): void {
            Gate::define('serve-the-pass', fn (?Authenticatable $user): bool => $user !== null);
            Gate::define('read-the-pass', fn (?Authenticatable $user): bool => $user !== null);
        });

        it('lets a command that declares no ability through, even for a guest', function (): void {
            expect(app(UnguardedTestBus::class)->dispatch(new OpenToAll))->toBeInstanceOf(WroteNothing::class);
        });

        it('refuses a command whose ability the caller lacks, with a 403', function (): void {
            try {
                app(AuthorizedTestBus::class)->dispatch(new ServeThePass);
            } catch (DomainException $exception) {
                expect($exception->status())->toBe(403)
                    ->and($exception->getMessage())->toBe('You are not allowed to do that.');

                return;
            }

            throw new RuntimeException('The command was not refused.');
        });

        it('lets a command through when the caller holds the ability', function (): void {
            $this->actingAs(new User);

            expect(app(AuthorizedTestBus::class)->dispatch(new ServeThePass))->toBeInstanceOf(WroteNothing::class);
        });

        it('guards a query the same way', function (): void {
            expect(fn () => app(AuthorizedTestQueryBus::class)->ask(new ReadThePass))
                ->toThrow(DomainException::class, 'You are not allowed to do that.');

            $this->actingAs(new User);

            expect(app(AuthorizedTestQueryBus::class)->ask(new ReadThePass))->toBeInstanceOf(PassContents::class);
        });
    });
});
