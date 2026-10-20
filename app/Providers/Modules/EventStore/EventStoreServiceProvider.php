<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore;

use App\Providers\Modules\EventStore\Application\AfterAppend;
use App\Providers\Modules\EventStore\Application\IntegrationBus;
use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Infrastructure\DatabaseCheckpointStore;
use App\Providers\Modules\EventStore\Infrastructure\DatabaseConsumerLag;
use App\Providers\Modules\EventStore\Infrastructure\DatabaseEventStore;
use App\Providers\Modules\EventStore\Infrastructure\DatabaseIntegrationBus;
use App\Providers\Modules\EventStore\Infrastructure\InProcessAfterAppend;
use App\Providers\Modules\SharedKernel\Application\Metrics\ConsumerLag;
use Illuminate\Support\ServiceProvider;

final class EventStoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/event_store.php', 'event_store');

        $this->app->singleton(AfterAppend::class, InProcessAfterAppend::class);
        $this->app->singleton(EventStore::class, DatabaseEventStore::class);
        $this->app->singleton(CheckpointStore::class, DatabaseCheckpointStore::class);
        $this->app->singleton(ConsumerLag::class, DatabaseConsumerLag::class);
        $this->app->singleton(IntegrationBus::class, DatabaseIntegrationBus::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
    }
}
