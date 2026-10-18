<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ServeDish;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use Illuminate\Http\JsonResponse;

final class ServeDishController
{
    public function __invoke(string $toServeId, FrontOfHouseCommandBus $bus): JsonResponse
    {
        return response()->json($bus->dispatch(new ServeDish(new DishAtThePassId($toServeId))));
    }
}
