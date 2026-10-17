<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\ServedLedger;
use App\Providers\BoundedContexts\Billing\Application\UnitOfWork;
use App\Providers\BoundedContexts\Billing\Domain\BillingException;
use App\Providers\BoundedContexts\Billing\Infrastructure\DatabaseBillRepository;
use App\Providers\BoundedContexts\Billing\Infrastructure\DatabaseServedLedger;
use App\Providers\BoundedContexts\Billing\Infrastructure\DatabaseUnitOfWork;
use App\Providers\BoundedContexts\Billing\Infrastructure\Processors\PrepareBillProcessor;
use App\Providers\BoundedContexts\Billing\Infrastructure\Processors\RecordServedDishes;
use App\Providers\BoundedContexts\EventStore\Application\AfterAppend;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/billing.php', 'billing');

        $this->app->bind(BillRepository::class, DatabaseBillRepository::class);
        $this->app->bind(ServedLedger::class, DatabaseServedLedger::class);
        $this->app->bind(UnitOfWork::class, DatabaseUnitOfWork::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $handler = $this->app->make(ExceptionHandler::class);
        if ($handler instanceof Handler) {
            $handler->dontReport(BillingException::class);
            $handler->renderable(function (BillingException $exception) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], $exception->status());
            });
        }

        $afterAppend = $this->app->make(AfterAppend::class);
        $afterAppend->listen(function (): void {
            $this->app->make(RecordServedDishes::class)->run();
        });
        $afterAppend->listen(function (): void {
            $this->app->make(PrepareBillProcessor::class)->run();
        });

        Route::middleware('api')
            ->prefix((string) config('billing.route_prefix'))
            ->group(__DIR__.'/routes/api.php');
    }
}
