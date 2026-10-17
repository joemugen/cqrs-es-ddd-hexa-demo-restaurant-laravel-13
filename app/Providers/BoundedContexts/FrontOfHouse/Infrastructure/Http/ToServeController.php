<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\ServeDish;
use App\Providers\BoundedContexts\FrontOfHouse\Application\ServeDishHandler;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Queries\ToServeQuery;
use Illuminate\Http\JsonResponse;

final class ToServeController
{
    public function index(ToServeQuery $query): JsonResponse
    {
        return response()->json($query->waiting());
    }

    public function serve(string $toServeId, ServeDishHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new ServeDish($toServeId)));
    }
}
