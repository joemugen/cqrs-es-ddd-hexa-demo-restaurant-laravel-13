<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse;

use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\OrderFulfilmentRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\PassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseQueryBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Console\ConsumeFrontOfHouseEvents;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Console\RunProjections;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseFloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseMenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseOrderFulfilmentRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabasePassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseTableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseTableRepository;
use App\Providers\Modules\EventStore\Infrastructure\EventConsumption;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class FrontOfHouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/front_of_house.php', 'front_of_house');

        $this->app->bind(TableRepository::class, DatabaseTableRepository::class);
        $this->app->bind(MenuRepository::class, DatabaseMenuRepository::class);
        $this->app->bind(TableOrderRepository::class, DatabaseTableOrderRepository::class);
        $this->app->bind(FloorPlan::class, DatabaseFloorPlan::class);
        $this->app->bind(PassRepository::class, DatabasePassRepository::class);
        $this->app->bind(OrderFulfilmentRepository::class, DatabaseOrderFulfilmentRepository::class);

        $this->app->singleton(FrontOfHouseCommandBus::class);
        $this->app->singleton(FrontOfHouseQueryBus::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $this->commands([RunProjections::class, ConsumeFrontOfHouseEvents::class]);

        $this->app->make(EventConsumption::class)->subscribe(__NAMESPACE__);

        Route::middleware('api')
            ->prefix((string) config('front_of_house.route_prefix'))
            ->group(__DIR__.'/routes/api.php');
    }
}
