<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Application;

interface IntegrationBus
{
    /**
     * Record a fact for other bounded contexts and tell in-process listeners.
     *
     * Listeners see a payload array. They do not share a class with the publisher.
     *
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $type, array $payload): void;

    /**
     * @param  callable(array<string, mixed>): void  $listener
     */
    public function listen(string $type, callable $listener): void;
}
