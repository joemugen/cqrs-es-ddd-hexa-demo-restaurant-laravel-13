<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Queries\MenuQuery;
use Illuminate\Http\JsonResponse;

final class MenuController
{
    public function __invoke(MenuQuery $query): JsonResponse
    {
        return response()->json($query->all());
    }
}
