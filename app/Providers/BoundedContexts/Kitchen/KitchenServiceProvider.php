<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen;

use App\Providers\BoundedContexts\EventStore\Application\AfterAppend;
use App\Providers\BoundedContexts\Kitchen\Application\DishCatalog;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenException;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\DatabaseDishCatalog;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\EventStoreKitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Processors\OpenKitchenTicketProcessor;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Projection\KitchenScreenProjector;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class KitchenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/kitchen.php', 'kitchen');

        $this->app->bind(KitchenTicketRepository::class, EventStoreKitchenTicketRepository::class);
        $this->app->bind(DishCatalog::class, DatabaseDishCatalog::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $handler = $this->app->make(ExceptionHandler::class);
        if ($handler instanceof Handler) {
            $handler->dontReport(KitchenException::class);
            $handler->renderable(function (KitchenException $exception) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], $exception->status());
            });
        }

        $afterAppend = $this->app->make(AfterAppend::class);
        $afterAppend->listen(function (): void {
            $this->app->make(KitchenScreenProjector::class)->catchUp();
        });
        $afterAppend->listen(function (): void {
            $this->app->make(OpenKitchenTicketProcessor::class)->run();
        });

        Route::middleware('api')
            ->prefix((string) config('kitchen.route_prefix'))
            ->group(__DIR__.'/routes/api.php');
    }
}
