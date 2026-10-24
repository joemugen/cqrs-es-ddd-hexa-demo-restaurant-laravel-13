<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Application\CoverLedgerRepository;
use App\Providers\BoundedContexts\Billing\Infrastructure\Bus\BillingCommandBus;
use App\Providers\BoundedContexts\Billing\Infrastructure\Bus\BillingQueryBus;
use App\Providers\BoundedContexts\Billing\Infrastructure\Console\ConsumeBillingEvents;
use App\Providers\BoundedContexts\Billing\Infrastructure\DatabaseBillRepository;
use App\Providers\BoundedContexts\Billing\Infrastructure\DatabaseCoverLedgerRepository;
use App\Providers\Modules\EventStore\Infrastructure\EventConsumption;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/billing.php', 'billing');

        $this->app->bind(BillRepository::class, DatabaseBillRepository::class);
        $this->app->bind(CoverLedgerRepository::class, DatabaseCoverLedgerRepository::class);

        $this->app->singleton(BillingCommandBus::class);
        $this->app->singleton(BillingQueryBus::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $this->commands([ConsumeBillingEvents::class]);

        $this->app->make(EventConsumption::class)->subscribe(__NAMESPACE__);

        Route::middleware('api')
            ->prefix((string) config('billing.route_prefix'))
            ->group(__DIR__.'/routes/api.php');
    }
}
