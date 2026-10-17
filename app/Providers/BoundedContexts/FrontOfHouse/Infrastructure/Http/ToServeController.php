<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ServeDish;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ServeDishHandler;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListToServe;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Query\ListToServeHandler;
use Illuminate\Http\JsonResponse;

final class ToServeController
{
    public function index(ListToServeHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new ListToServe));
    }

    public function serve(string $toServeId, ServeDishHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new ServeDish($toServeId)));
    }
}
