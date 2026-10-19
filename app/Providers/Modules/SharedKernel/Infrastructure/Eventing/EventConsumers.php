<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Infrastructure\Eventing;

use App\Providers\Modules\SharedKernel\Application\EventConsumer;
use Illuminate\Contracts\Container\Container;

/**
 * The one way to get a consumer to run: resolved from the container and guarded by its lock.
 */
final readonly class EventConsumers
{
    public function __construct(private Container $container, private int $lockSeconds) {}

    /**
     * @param  class-string<EventConsumer>  $consumer
     */
    public function make(string $consumer): EventConsumer
    {
        return new ExclusiveConsumer($this->container->make($consumer), $this->lockSeconds);
    }
}
