<?php

describe('the shared kernel boundaries', function () {
    arch('the contracts do not depend on Laravel')
        ->expect([
            'App\Providers\Modules\SharedKernel\Domain',
            'App\Providers\Modules\SharedKernel\Application',
        ])
        ->not->toUse('Illuminate');

    arch('the shared kernel does not import a bounded context')
        ->expect('App\Providers\Modules\SharedKernel')
        ->not->toUse([
            'App\Providers\Modules\EventStore',
            'App\Providers\BoundedContexts\FrontOfHouse',
            'App\Providers\BoundedContexts\Kitchen',
            'App\Providers\BoundedContexts\Billing',
        ]);
});
