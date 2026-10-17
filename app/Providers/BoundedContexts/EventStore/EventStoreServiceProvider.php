<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore;

use App\Providers\BoundedContexts\EventStore\Application\AfterAppend;
use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\EventStore\Domain\CheckpointStore;
use App\Providers\BoundedContexts\EventStore\Domain\EventStore;
use App\Providers\BoundedContexts\EventStore\Infrastructure\DatabaseCheckpointStore;
use App\Providers\BoundedContexts\EventStore\Infrastructure\DatabaseEventStore;
use App\Providers\BoundedContexts\EventStore\Infrastructure\DatabaseIntegrationBus;
use App\Providers\BoundedContexts\EventStore\Infrastructure\InProcessAfterAppend;
use Illuminate\Support\ServiceProvider;

final class EventStoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/event_store.php', 'event_store');

        $this->app->singleton(AfterAppend::class, InProcessAfterAppend::class);
        $this->app->singleton(EventStore::class, DatabaseEventStore::class);
        $this->app->singleton(CheckpointStore::class, DatabaseCheckpointStore::class);
        $this->app->singleton(IntegrationBus::class, DatabaseIntegrationBus::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
    }
}
