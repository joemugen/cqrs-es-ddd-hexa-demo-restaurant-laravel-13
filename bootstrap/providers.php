<?php

use App\Providers\AppServiceProvider;
use App\Providers\BoundedContexts\EventStore\EventStoreServiceProvider;

return [
    AppServiceProvider::class,
    EventStoreServiceProvider::class,
];
