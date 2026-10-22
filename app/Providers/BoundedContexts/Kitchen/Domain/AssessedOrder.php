<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Domain;

use App\Providers\BoundedContexts\Kitchen\Domain\Event\LinesUnavailable;

/**
 * What the kitchen can cook of an order, and the fact that tells what it cannot.
 */
final readonly class AssessedOrder
{
    /**
     * @param  list<TicketLine>  $cookable
     */
    public function __construct(
        public array $cookable,
        public ?LinesUnavailable $unavailable,
    ) {}
}
