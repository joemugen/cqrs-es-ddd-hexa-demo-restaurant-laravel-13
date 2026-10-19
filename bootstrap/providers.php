<?php

use App\Providers\AppServiceProvider;
use App\Providers\BoundedContexts\Billing\BillingServiceProvider;
use App\Providers\BoundedContexts\FrontOfHouse\FrontOfHouseServiceProvider;
use App\Providers\BoundedContexts\Kitchen\KitchenServiceProvider;
use App\Providers\Modules\EventStore\EventStoreServiceProvider;
use App\Providers\Modules\SharedKernel\SharedKernelServiceProvider;

return [
    AppServiceProvider::class,
    SharedKernelServiceProvider::class,
    EventStoreServiceProvider::class,
    FrontOfHouseServiceProvider::class,
    KitchenServiceProvider::class,
    BillingServiceProvider::class,
];
