<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Application\Query\ListKitchenScreen;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Query\ListKitchenScreenHandler;
use Illuminate\Http\JsonResponse;

final class KitchenScreenController
{
    public function __invoke(ListKitchenScreenHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new ListKitchenScreen));
    }
}
