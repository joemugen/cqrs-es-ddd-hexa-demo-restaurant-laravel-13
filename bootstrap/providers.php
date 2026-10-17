<?php

use App\Providers\AppServiceProvider;
use App\Providers\BoundedContexts\EventStore\EventStoreServiceProvider;
use App\Providers\BoundedContexts\FrontOfHouse\FrontOfHouseServiceProvider;

return [
    AppServiceProvider::class,
    EventStoreServiceProvider::class,
    FrontOfHouseServiceProvider::class,
];
