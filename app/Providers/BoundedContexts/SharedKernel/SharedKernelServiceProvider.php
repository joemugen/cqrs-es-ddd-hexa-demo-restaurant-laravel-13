<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel;

use App\Providers\BoundedContexts\SharedKernel\Domain\DomainException;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Bus\HandlerDiscovery;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Console\CacheHandlers;
use App\Providers\BoundedContexts\SharedKernel\Infrastructure\Console\ClearHandlers;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;

final class SharedKernelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            HandlerDiscovery::class,
            fn ($app): HandlerDiscovery => new HandlerDiscovery($app->bootstrapPath('cache/bus-handlers.php')),
        );
    }

    public function boot(): void
    {
        $this->commands([CacheHandlers::class, ClearHandlers::class]);
        $this->optimizes(optimize: 'bus:cache', clear: 'bus:clear', key: 'bus');

        $handler = $this->app->make(ExceptionHandler::class);
        if ($handler instanceof Handler) {
            $handler->dontReport(DomainException::class);
            $handler->renderable(function (DomainException $exception) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], $exception->status());
            });
        }
    }
}
