<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Domain;

/**
 * For an aggregate that is saved as its state, not rebuilt from its events: it records the typed facts of what it
 * decided, and whoever saves it releases them to be told to the other contexts.
 */
trait RecordsEvents
{
    /** @var list<TypedDomainEvent> */
    private array $recorded = [];

    protected function recordThat(TypedDomainEvent $fact): void
    {
        $this->recorded[] = $fact;
    }

    /**
     * @return list<TypedDomainEvent>
     */
    public function releaseEvents(): array
    {
        $released = $this->recorded;
        $this->recorded = [];

        return $released;
    }
}
