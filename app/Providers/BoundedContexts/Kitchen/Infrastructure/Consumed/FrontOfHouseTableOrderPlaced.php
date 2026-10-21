<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Consumed;

use App\Providers\BoundedContexts\Kitchen\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * An order was placed: the kitchen opens its ticket with the dishes it can cook.
 */
#[Consumes(ConsumedEvents::FRONT_OF_HOUSE_TABLE_ORDER_PLACED, version: 1)]
final readonly class FrontOfHouseTableOrderPlaced extends OrderToCook {}
