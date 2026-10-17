<?php

arch('the shared kernel does not depend on Laravel')
    ->expect('App\Providers\BoundedContexts\SharedKernel')
    ->not->toUse('Illuminate');

arch('the shared kernel does not import a bounded context')
    ->expect('App\Providers\BoundedContexts\SharedKernel')
    ->not->toUse([
        'App\Providers\BoundedContexts\EventStore',
        'App\Providers\BoundedContexts\FrontOfHouse',
        'App\Providers\BoundedContexts\Kitchen',
        'App\Providers\BoundedContexts\Billing',
    ]);
