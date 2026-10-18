<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse;

use App\Providers\BoundedContexts\EventStore\Application\AfterAppend;
use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\MenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\PassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\UnitOfWork;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Console\RunProjections;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseFloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseMenuRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabasePassRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseTableOrderRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseTableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseUnitOfWork;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Processors\FreeTableProcessor;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Processors\MarkMenuItemUnavailableProcessor;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Projection\ProjectToServeList;
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
        $this->app->bind(UnitOfWork::class, DatabaseUnitOfWork::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $this->commands([RunProjections::class]);

        $afterAppend = $this->app->make(AfterAppend::class);
        $afterAppend->listen(function (): void {
            ProjectToServeList::dispatch();
        });
        $afterAppend->listen(function (): void {
            $this->app->make(MarkMenuItemUnavailableProcessor::class)->run();
        });
        $afterAppend->listen(function (): void {
            $this->app->make(FreeTableProcessor::class)->run();
        });

        Route::middleware('api')
            ->prefix((string) config('front_of_house.route_prefix'))
            ->group(__DIR__.'/routes/api.php');
    }
}
