<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

/**
 * What the kitchen can cook of an order, and what it cannot.
 */
final readonly class LinesAvailability
{
    /**
     * @param  list<TicketLine>  $available
     * @param  list<UnavailableLine>  $unavailable
     */
    public function __construct(
        public array $available,
        public array $unavailable,
    ) {}
}
