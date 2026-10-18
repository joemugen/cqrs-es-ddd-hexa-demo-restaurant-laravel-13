<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListFloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseQueryBus;
use Illuminate\Http\JsonResponse;

final class ListFloorPlanController
{
    public function __invoke(FrontOfHouseQueryBus $bus): JsonResponse
    {
        return response()->json($bus->ask(new ListFloorPlan));
    }
}
