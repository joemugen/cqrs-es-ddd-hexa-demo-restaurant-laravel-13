<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel;

use App\Providers\Modules\SharedKernel\Application\Bus\Exception\IdempotencyKeyReused;
use App\Providers\Modules\SharedKernel\Application\Bus\Exception\NotAuthorized;
use App\Providers\Modules\SharedKernel\Application\Concurrency\ConcurrencyConflict;
use App\Providers\Modules\SharedKernel\Application\Concurrency\PreconditionFailed;
use App\Providers\Modules\SharedKernel\Application\Concurrency\PreconditionRequired;
use App\Providers\Modules\SharedKernel\Application\Exception\Refusal;
use App\Providers\Modules\SharedKernel\Application\Metrics\MetricsRecorder;
use App\Providers\Modules\SharedKernel\Domain\DomainException;
use App\Providers\Modules\SharedKernel\Domain\NotFound;
use App\Providers\Modules\SharedKernel\Domain\RuleViolation;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use App\Providers\Modules\SharedKernel\Infrastructure\Console\CacheContexts;
use App\Providers\Modules\SharedKernel\Infrastructure\Console\ClearContexts;
use App\Providers\Modules\SharedKernel\Infrastructure\Console\ShowMetrics;
use App\Providers\Modules\SharedKernel\Infrastructure\Http\RefusalStatuses;
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

        $this->app->singleton(RefusalStatuses::class, function (): RefusalStatuses {
            $statuses = new RefusalStatuses;
            $statuses->register([
                NotFound::class => 404,
                RuleViolation::class => 422,
                ConcurrencyConflict::class => 409,
                PreconditionFailed::class => 412,
                PreconditionRequired::class => 428,
                NotAuthorized::class => 403,
                IdempotencyKeyReused::class => 422,
            ]);

            return $statuses;
        });

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
            $statuses = $this->app->make(RefusalStatuses::class);
            $handler->dontReport([DomainException::class, Refusal::class]);
            $render = fn (DomainException|Refusal $exception) => response()->json([
                'message' => $exception->getMessage(),
            ], $statuses->of($exception));
            $handler->renderable(fn (DomainException $exception) => $render($exception));
            $handler->renderable(fn (Refusal $exception) => $render($exception));
        }
    }
}
