<?php

describe('the kitchen boundaries', function () {
    arch('the kitchen domain does not depend on Laravel')
        ->expect('App\Providers\BoundedContexts\Kitchen\Domain')
        ->not->toUse('Illuminate');

    arch('the kitchen does not import the dining room or the till')
        ->expect('App\Providers\BoundedContexts\Kitchen')
        ->not->toUse([
            'App\Providers\BoundedContexts\FrontOfHouse',
            'App\Providers\BoundedContexts\Billing',
        ]);
});
