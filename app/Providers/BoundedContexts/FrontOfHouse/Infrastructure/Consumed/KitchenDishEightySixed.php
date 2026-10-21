<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Consumed;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\ConsumedEvents;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;
use App\Providers\Modules\SharedKernel\Application\Publication\Consumes;

/**
 * The kitchen ran out of a dish: the menu stops offering it.
 */
#[Consumes(ConsumedEvents::KITCHEN_DISH_EIGHTY_SIXED, version: 1)]
final readonly class KitchenDishEightySixed implements ConsumedMessage
{
    public function __construct(public DishName $dishName) {}

    public static function fromPayload(array $payload): static
    {
        return new self(new DishName((string) $payload['dishName']));
    }
}
