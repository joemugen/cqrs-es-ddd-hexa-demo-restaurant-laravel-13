<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel;

use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use App\Providers\Modules\SharedKernel\Infrastructure\Console\CacheContexts;
use App\Providers\Modules\SharedKernel\Infrastructure\Console\ClearContexts;
use App\Providers\Modules\SharedKernel\Infrastructure\Eventing\EventConsumers;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;

final class SharedKernelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/consumption.php', 'consumption');

        $this->app->singleton(
            EventConsumers::class,
            fn ($app): EventConsumers => new EventConsumers($app, (int) config('consumption.lock_seconds')),
        );
        $this->app->singleton(
            ContextDiscovery::class,
            fn ($app): ContextDiscovery => new ContextDiscovery($app->bootstrapPath('cache/contexts.php')),
        );
    }

    public function boot(): void
    {
        $this->commands([CacheContexts::class, ClearContexts::class]);
        $this->optimizes(optimize: 'contexts:cache', clear: 'contexts:clear', key: 'contexts');

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
