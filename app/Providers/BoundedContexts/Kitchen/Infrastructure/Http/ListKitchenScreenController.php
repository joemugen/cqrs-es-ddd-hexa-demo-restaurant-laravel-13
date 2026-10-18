<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Application\Query\ListKitchenScreen;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenQueryBus;
use Illuminate\Http\JsonResponse;

final class ListKitchenScreenController
{
    public function __invoke(KitchenQueryBus $bus): JsonResponse
    {
        return response()->json($bus->ask(new ListKitchenScreen));
    }
}
