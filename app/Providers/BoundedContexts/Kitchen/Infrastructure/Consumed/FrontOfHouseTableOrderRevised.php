<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Consumed;

use App\Providers\BoundedContexts\Kitchen\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * The guests chose replacements: the kitchen adds the dishes it can cook to the ticket.
 */
#[Consumes(ConsumedEvents::FRONT_OF_HOUSE_TABLE_ORDER_REVISED, version: 1)]
final readonly class FrontOfHouseTableOrderRevised extends OrderToCook {}
