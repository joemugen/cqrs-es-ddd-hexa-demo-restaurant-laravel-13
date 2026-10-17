<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListFloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query\ListFloorPlanHandler;
use Illuminate\Http\JsonResponse;

final class FloorPlanController
{
    public function __invoke(ListFloorPlanHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new ListFloorPlan));
    }
}
