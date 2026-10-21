<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Application;

use App\Providers\Modules\EventStore\Domain\IntegrationEvent;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Domain\EventType;
use Closure;

interface IntegrationBus
{
    /**
     * Record a contract for other bounded contexts and tell in-process listeners. The contract names itself with
     * `#[PublishedAs]`.
     *
     * @return int where the fact sits in the log: the logical clock a context can compare other facts with
     */
    public function publish(IntegrationContract $contract): int;

    /**
     * @param  Closure(IntegrationEvent): void  $listener
     */
    public function listen(EventType $type, Closure $listener): void;
}
