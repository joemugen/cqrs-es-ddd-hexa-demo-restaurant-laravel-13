<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\ReviseOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\Requests\RequestedLines;
use App\Providers\Modules\SharedKernel\Infrastructure\Http\IdempotencyHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReviseOrderController
{
    public function __invoke(Request $request, string $tableOrderId, FrontOfHouseCommandBus $bus): JsonResponse
    {
        return response()->json($bus->dispatch(new ReviseOrder(
            new TableOrderId($tableOrderId),
            RequestedLines::from($request),
            IdempotencyHeader::keyOf($request),
        )));
    }
}
