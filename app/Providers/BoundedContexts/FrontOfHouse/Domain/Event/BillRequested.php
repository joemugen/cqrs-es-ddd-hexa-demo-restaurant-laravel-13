<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain\Event;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * The guests of a table asked for their bill.
 */
final readonly class BillRequested implements TypedDomainEvent
{
    public function __construct(public TableId $tableId) {}
}
