<?php

describe('the billing boundaries', function () {
    arch('the billing domain does not depend on Laravel')
        ->expect('App\Providers\BoundedContexts\Billing\Domain')
        ->not->toUse('Illuminate');

    arch('the till does not import the dining room or the kitchen')
        ->expect('App\Providers\BoundedContexts\Billing')
        ->not->toUse([
            'App\Providers\BoundedContexts\FrontOfHouse',
            'App\Providers\BoundedContexts\Kitchen',
        ]);
});
