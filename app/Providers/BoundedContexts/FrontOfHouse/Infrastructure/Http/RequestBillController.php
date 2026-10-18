<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\RequestBill;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use Illuminate\Http\JsonResponse;

final class RequestBillController
{
    public function __invoke(string $tableId, FrontOfHouseCommandBus $bus): JsonResponse
    {
        return response()->json($bus->dispatch(new RequestBill(new TableId($tableId))));
    }
}
