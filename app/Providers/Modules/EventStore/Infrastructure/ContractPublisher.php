<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Infrastructure;

use App\Providers\Modules\EventStore\Application\IntegrationBus;
use App\Providers\Modules\EventStore\Domain\IntegrationEvent;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\PublishedAs;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;
use App\Providers\Modules\SharedKernel\Domain\DomainEvents;
use App\Providers\Modules\SharedKernel\Domain\EventType;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use ReflectionClass;

/**
 * The fuse between the facts of a domain and what the other contexts read. A typed fact that an aggregate recorded is
 * translated by every contract that declares `#[Translates]` for it, and told on the integration bus. A fact with no
 * contract stays in its stream. It runs where the aggregate is saved, so the fact and its contract commit together.
 */
final class ContractPublisher
{
    /** @var array<class-string<TypedDomainEvent>, list<class-string<IntegrationContract>>>|null */
    private ?array $contractsByEvent = null;

    public function __construct(
        private readonly ContextDiscovery $discovery,
        private readonly IntegrationBus $bus,
    ) {}

    public function publish(DomainEvents $events): void
    {
        foreach ($events as $event) {
            foreach ($this->contractsFor($event->fact) as $contract) {
                $published = $this->publishedAs($contract);

                $this->bus->publish(new IntegrationEvent(
                    new EventType($published->type),
                    $contract::from($event->fact)->toPayload(),
                    $published->version,
                ));
            }
        }
    }

    /**
     * @return list<class-string<IntegrationContract>>
     */
    private function contractsFor(?TypedDomainEvent $fact): array
    {
        return $fact === null ? [] : ($this->contractsByEvent()[$fact::class] ?? []);
    }

    /**
     * @return array<class-string<TypedDomainEvent>, list<class-string<IntegrationContract>>>
     */
    private function contractsByEvent(): array
    {
        if ($this->contractsByEvent !== null) {
            return $this->contractsByEvent;
        }

        $this->contractsByEvent = [];

        foreach (glob(app_path('Providers/BoundedContexts/*'), GLOB_ONLYDIR) ?: [] as $contextRoot) {
            foreach ($this->discovery->contracts($contextRoot) as $contract) {
                $translated = (new ReflectionClass($contract))->getAttributes(Translates::class)[0]->newInstance()->event;
                $this->contractsByEvent[$translated][] = $contract;
            }
        }

        return $this->contractsByEvent;
    }

    private function publishedAs(string $contract): PublishedAs
    {
        return (new ReflectionClass($contract))->getAttributes(PublishedAs::class)[0]->newInstance();
    }
}
