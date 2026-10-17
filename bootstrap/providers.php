<?php

use App\Providers\AppServiceProvider;
use App\Providers\BoundedContexts\EventStore\EventStoreServiceProvider;
use App\Providers\BoundedContexts\FrontOfHouse\FrontOfHouseServiceProvider;
use App\Providers\BoundedContexts\Kitchen\KitchenServiceProvider;

return [
    AppServiceProvider::class,
    EventStoreServiceProvider::class,
    FrontOfHouseServiceProvider::class,
    KitchenServiceProvider::class,
];
