<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Infrastructure;

use App\Providers\Modules\EventStore\Application\ContractPublisher;
use App\Providers\Modules\EventStore\Application\IntegrationBus;
use App\Providers\Modules\SharedKernel\Application\Publication\IntegrationContract;
use App\Providers\Modules\SharedKernel\Application\Publication\Translates;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;
use App\Providers\Modules\SharedKernel\Infrastructure\Bus\ContextDiscovery;
use ReflectionClass;

/**
 * The fuse between the facts of a domain and what the other contexts read. A typed fact that an aggregate recorded is
 * translated by every contract that declares `#[Translates]` for it, and told on the integration bus. A fact with no
 * contract stays where it was recorded. It runs where the aggregate is saved, so the fact and its contract commit together.
 */
final class ConventionContractPublisher implements ContractPublisher
{
    /** @var array<class-string<TypedDomainEvent>, list<class-string<IntegrationContract>>>|null */
    private ?array $contractsByEvent = null;

    public function __construct(
        private readonly ContextDiscovery $discovery,
        private readonly IntegrationBus $bus,
    ) {}

    public function publish(array $facts): array
    {
        $positions = [];

        foreach ($facts as $fact) {
            foreach ($this->contractsFor($fact) as $contract) {
                $positions[] = $this->bus->publish($contract::from($fact));
            }
        }

        return $positions;
    }

    /**
     * @return list<class-string<IntegrationContract>>
     */
    private function contractsFor(TypedDomainEvent $fact): array
    {
        return $this->contractsByEvent()[$fact::class] ?? [];
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

        foreach ($this->discovery->contexts() as $context) {
            foreach ($this->discovery->contracts($context) as $contract) {
                $translated = (new ReflectionClass($contract))->getAttributes(Translates::class)[0]->newInstance()->event;
                $this->contractsByEvent[$translated][] = $contract;
            }
        }

        return $this->contractsByEvent;
    }
}
