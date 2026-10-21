<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application;

use App\Providers\BoundedContexts\Kitchen\Domain\LinesAvailability;
use App\Providers\BoundedContexts\Kitchen\Domain\OrderedLine;

interface DishCatalog
{
    /**
     * Translate the ordered lines into dishes the kitchen can cook, and name the ones it cannot. No price comes across.
     *
     * An order is judged where it sits in the event log: a dish declared 86 after $asOfPosition was still
     * available when the order was placed, however late a worker reads it.
     *
     * @param  list<OrderedLine>  $ordered
     * @param  int  $asOfPosition  the position of the order in the log
     */
    public function linesFor(array $ordered, int $asOfPosition): LinesAvailability;
}
