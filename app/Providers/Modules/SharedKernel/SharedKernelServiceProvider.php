<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel;

use App\Providers\Modules\SharedKernel\Application\Metrics\MetricsRecorder;
use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use App\Providers\Modules\SharedKernel\Infrastructure\Console\CacheContexts;
use App\Providers\Modules\SharedKernel\Infrastructure\Console\ClearContexts;
use App\Providers\Modules\SharedKernel\Infrastructure\Console\ShowMetrics;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\DatabaseMetricsRecorder;
use App\Providers\Modules\SharedKernel\Infrastructure\Metrics\SafeMetricsRecorder;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;

final class SharedKernelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/consumption.php', 'consumption');
        $this->mergeConfigFrom(__DIR__.'/config/contexts.php', 'contexts');

        $this->app->singleton(MetricsRecorder::class, fn ($app): MetricsRecorder => new SafeMetricsRecorder($app->make(DatabaseMetricsRecorder::class)));

        $this->app->singleton(
            ContextDiscovery::class,
            fn ($app): ContextDiscovery => new ContextDiscovery($app->bootstrapPath('cache/contexts.php'), $app['config']->get('contexts')),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        $this->commands([CacheContexts::class, ClearContexts::class, ShowMetrics::class]);
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
