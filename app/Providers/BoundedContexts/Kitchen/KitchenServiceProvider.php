<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen;

use App\Providers\BoundedContexts\Kitchen\Application\DishCatalog;
use App\Providers\BoundedContexts\Kitchen\Application\DishRepository;
use App\Providers\BoundedContexts\Kitchen\Application\KitchenTicketRepository;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenCommandBus;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenQueryBus;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Console\ConsumeKitchenEvents;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\DatabaseDishCatalog;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\DatabaseDishRepository;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\EventStoreKitchenTicketRepository;
use App\Providers\Modules\EventStore\Infrastructure\EventConsumption;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class KitchenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/kitchen.php', 'kitchen');

        $this->app->bind(KitchenTicketRepository::class, EventStoreKitchenTicketRepository::class);
        $this->app->bind(DishCatalog::class, DatabaseDishCatalog::class);
        $this->app->bind(DishRepository::class, DatabaseDishRepository::class);

        $this->app->singleton(KitchenCommandBus::class);
        $this->app->singleton(KitchenQueryBus::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $this->commands([ConsumeKitchenEvents::class]);

        $this->app->make(EventConsumption::class)->subscribe(__NAMESPACE__);

        Route::middleware('api')
            ->prefix((string) config('kitchen.route_prefix'))
            ->group(__DIR__.'/routes/api.php');
    }
}
