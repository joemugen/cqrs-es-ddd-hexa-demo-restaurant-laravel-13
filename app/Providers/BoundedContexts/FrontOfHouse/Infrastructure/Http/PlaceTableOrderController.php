<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\PlaceTableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\Requests\RequestedLines;
use App\Providers\Modules\SharedKernel\Infrastructure\Http\IdempotencyHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PlaceTableOrderController
{
    public function __invoke(Request $request, string $tableId, FrontOfHouseCommandBus $bus): JsonResponse
    {
        return response()->json($bus->dispatch(new PlaceTableOrder(
            tableOrderId: TableOrderId::derivedFrom(
                new TableId($tableId),
                (string) (IdempotencyHeader::keyOf($request) ?? Str::uuid()),
            ),
            tableId: new TableId($tableId),
            lines: RequestedLines::from($request),
            idempotencyKey: IdempotencyHeader::keyOf($request),
        )));
    }
}
