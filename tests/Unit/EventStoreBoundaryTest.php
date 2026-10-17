<?php

arch('the event store domain does not depend on Laravel')
    ->expect('App\Providers\BoundedContexts\EventStore\Domain')
    ->not->toUse('Illuminate');
