<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Application\Command\DeclareDishEightySixed;
use App\Providers\BoundedContexts\Kitchen\Domain\DishId;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenCommandBus;
use Illuminate\Http\JsonResponse;

final class EightySixController
{
    public function store(string $dishId, KitchenCommandBus $bus): JsonResponse
    {
        return response()->json($bus->dispatch(new DeclareDishEightySixed(new DishId($dishId))));
    }
}
