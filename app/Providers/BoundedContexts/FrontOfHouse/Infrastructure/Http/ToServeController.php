<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ServeDish;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListToServe;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseQueryBus;
use Illuminate\Http\JsonResponse;

final class ToServeController
{
    public function index(FrontOfHouseQueryBus $bus): JsonResponse
    {
        return response()->json($bus->ask(new ListToServe));
    }

    public function serve(string $toServeId, FrontOfHouseCommandBus $bus): JsonResponse
    {
        return response()->json($bus->dispatch(new ServeDish(new DishAtThePassId($toServeId))));
    }
}
