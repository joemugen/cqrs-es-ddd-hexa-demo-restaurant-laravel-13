<?php

describe('the dining room boundaries', function () {
    arch('the front of house domain does not depend on Laravel')
        ->expect('App\Providers\BoundedContexts\FrontOfHouse\Domain')
        ->not->toUse('Illuminate');

    arch('the dining room does not import the kitchen or the till')
        ->expect('App\Providers\BoundedContexts\FrontOfHouse')
        ->not->toUse([
            'App\Providers\BoundedContexts\Kitchen',
            'App\Providers\BoundedContexts\Billing',
        ]);
});
