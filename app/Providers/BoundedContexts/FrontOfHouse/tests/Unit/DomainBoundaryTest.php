<?php

arch('the front of house domain does not depend on Laravel')
    ->expect('App\Providers\BoundedContexts\FrontOfHouse\Domain')
    ->not->toUse('Illuminate');
