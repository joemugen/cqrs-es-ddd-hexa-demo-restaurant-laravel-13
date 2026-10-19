<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Application;

use App\Providers\BoundedContexts\SharedKernel\Domain\EventType;
use App\Providers\Modules\EventStore\Domain\IntegrationEvent;
use Closure;

interface IntegrationBus
{
    /**
     * Record a fact for other bounded contexts and tell in-process listeners.
     */
    public function publish(IntegrationEvent $event): void;

    /**
     * @param  Closure(IntegrationEvent): void  $listener
     */
    public function listen(EventType $type, Closure $listener): void;
}
