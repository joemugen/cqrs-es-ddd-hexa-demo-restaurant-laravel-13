<?php

arch('the event store domain does not depend on Laravel')
    ->expect('App\Providers\BoundedContexts\EventStore\Domain')
    ->not->toUse('Illuminate');

arch('the event store does not import a bounded context')
    ->expect('App\Providers\BoundedContexts\EventStore')
    ->not->toUse([
        'App\Providers\BoundedContexts\FrontOfHouse',
        'App\Providers\BoundedContexts\Kitchen',
        'App\Providers\BoundedContexts\Billing',
    ]);
