<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ListMenu;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseQueryBus;
use Illuminate\Http\JsonResponse;

final class MenuController
{
    public function __invoke(FrontOfHouseQueryBus $bus): JsonResponse
    {
        return response()->json($bus->ask(new ListMenu));
    }
}
