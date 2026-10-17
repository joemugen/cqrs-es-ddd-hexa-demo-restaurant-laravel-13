<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Infrastructure;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\EventStore\Domain\EventStore;

final class DatabaseIntegrationBus implements IntegrationBus
{
    /** @var array<string, list<callable(array<string, mixed>): void>> */
    private array $listeners = [];

    public function __construct(private EventStore $events) {}

    public function publish(string $type, array $payload): void
    {
        $this->events->append('integration-'.bin2hex(random_bytes(8)), 0, [
            new GenericMessage($type, $payload, now()->utc()->format('Y-m-d H:i:s')),
        ]);

        foreach ($this->listeners[$type] ?? [] as $listener) {
            $listener($payload);
        }
    }

    public function listen(string $type, callable $listener): void
    {
        $this->listeners[$type][] = $listener;
    }
}
