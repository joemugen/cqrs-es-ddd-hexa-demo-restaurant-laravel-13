<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse;

use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Application\TableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Application\UnitOfWork;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\FrontOfHouseException;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseFloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseTableRepository;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\DatabaseUnitOfWork;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class FrontOfHouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/front_of_house.php', 'front_of_house');

        $this->app->bind(TableRepository::class, DatabaseTableRepository::class);
        $this->app->bind(FloorPlan::class, DatabaseFloorPlan::class);
        $this->app->bind(UnitOfWork::class, DatabaseUnitOfWork::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $handler = $this->app->make(ExceptionHandler::class);
        if ($handler instanceof Handler) {
            $handler->dontReport(FrontOfHouseException::class);
            $handler->renderable(function (FrontOfHouseException $exception) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], $exception->status());
            });
        }

        Route::middleware('api')
            ->prefix((string) config('front_of_house.route_prefix'))
            ->group(__DIR__.'/routes/api.php');
    }
}
