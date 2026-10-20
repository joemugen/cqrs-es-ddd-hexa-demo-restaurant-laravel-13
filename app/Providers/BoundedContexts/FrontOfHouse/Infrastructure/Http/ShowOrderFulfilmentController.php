<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Query\ShowOrderFulfilment;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseQueryBus;
use Illuminate\Http\JsonResponse;

final class ShowOrderFulfilmentController
{
    public function __invoke(string $tableOrderId, FrontOfHouseQueryBus $bus): JsonResponse
    {
        return response()->json($bus->ask(new ShowOrderFulfilment(new TableOrderId($tableOrderId))));
    }
}
