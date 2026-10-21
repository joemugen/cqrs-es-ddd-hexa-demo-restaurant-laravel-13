<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Infrastructure;

use App\Providers\Modules\EventStore\Application\IntegrationBus;
use App\Providers\Modules\EventStore\Domain\EventStore;
use App\Providers\Modules\EventStore\Domain\IntegrationEvent;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Domain\DomainEvent;
use App\Providers\Modules\SharedKernel\Domain\DomainEvents;
use App\Providers\Modules\SharedKernel\Domain\EventType;
use App\Providers\Modules\SharedKernel\Domain\StreamId;
use Closure;
use ReflectionClass;

final class DatabaseIntegrationBus implements IntegrationBus
{
    /** @var array<string, list<Closure(IntegrationEvent): void>> */
    private array $listeners = [];

    public function __construct(private EventStore $events) {}

    public function publish(IntegrationContract $contract): int
    {
        $published = (new ReflectionClass($contract))->getAttributes(PublishedAs::class)[0]->newInstance();
        $event = new IntegrationEvent(new EventType($published->type), $contract->toPayload(), $published->version);

        $position = $this->events->append(
            new StreamId('integration-'.bin2hex(random_bytes(8))),
            0,
            DomainEvents::of(DomainEvent::now((string) $event->type, $event->payload, $event->version)),
        );

        foreach ($this->listeners[(string) $event->type] ?? [] as $listener) {
            $listener($event);
        }

        return $position;
    }

    public function listen(EventType $type, Closure $listener): void
    {
        $this->listeners[(string) $type][] = $listener;
    }
}
