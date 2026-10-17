<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;
use App\Providers\BoundedContexts\Kitchen\Domain\TicketLine;

interface DishCatalog
{
    /**
     * Translate an ordered line into a dish the kitchen can cook. No price comes across.
     *
     * @param  list<OrderedLine>  $ordered
     * @return list<TicketLine>
     */
    public function linesFor(array $ordered): array;
}
