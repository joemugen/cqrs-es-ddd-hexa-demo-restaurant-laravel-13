<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Infrastructure\Queries\KitchenScreenQuery;
use Illuminate\Http\JsonResponse;

final class KitchenScreenController
{
    public function __invoke(KitchenScreenQuery $query): JsonResponse
    {
        return response()->json($query->all());
    }
}
