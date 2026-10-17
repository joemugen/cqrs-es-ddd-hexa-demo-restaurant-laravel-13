<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Infrastructure;

use App\Providers\BoundedContexts\EventStore\Application\IntegrationBus;
use App\Providers\BoundedContexts\EventStore\Domain\EventStore;
use App\Providers\BoundedContexts\EventStore\Domain\IntegrationEvent;
use App\Providers\BoundedContexts\SharedKernel\Domain\DomainEvent;
use App\Providers\BoundedContexts\SharedKernel\Domain\DomainEvents;
use App\Providers\BoundedContexts\SharedKernel\Domain\EventType;
use App\Providers\BoundedContexts\SharedKernel\Domain\StreamId;
use Closure;

final class DatabaseIntegrationBus implements IntegrationBus
{
    /** @var array<string, list<Closure(IntegrationEvent): void>> */
    private array $listeners = [];

    public function __construct(private EventStore $events) {}

    public function publish(IntegrationEvent $event): void
    {
        $this->events->append(
            new StreamId('integration-'.bin2hex(random_bytes(8))),
            0,
            DomainEvents::of(DomainEvent::now((string) $event->type, $event->payload)),
        );

        foreach ($this->listeners[(string) $event->type] ?? [] as $listener) {
            $listener($event);
        }
    }

    public function listen(EventType $type, Closure $listener): void
    {
        $this->listeners[(string) $type][] = $listener;
    }
}
