<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Queries\FloorPlanQuery;
use Illuminate\Http\JsonResponse;

final class FloorPlanController
{
    public function __invoke(FloorPlanQuery $query): JsonResponse
    {
        return response()->json($query->all());
    }
}
