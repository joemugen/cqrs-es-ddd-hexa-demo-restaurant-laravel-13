<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\PlaceTableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\Modules\SharedKernel\Infrastructure\Http\IdempotencyHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PlaceTableOrderController
{
    public function __invoke(Request $request, string $tableId, FrontOfHouseCommandBus $bus): JsonResponse
    {
        $validated = $request->validate([
            'lines' => ['present', 'array'],
            'lines.*.menuItemId' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'integer'],
            'lines.*.course' => ['required', 'string'],
        ]);

        $lines = array_map(fn (array $line): RequestedLine => new RequestedLine(
            new MenuItemId($line['menuItemId']),
            (int) $line['quantity'],
            $line['course'],
        ), $validated['lines']);

        return response()->json($bus->dispatch(new PlaceTableOrder(
            tableOrderId: TableOrderId::derivedFrom(
                new TableId($tableId),
                (string) (IdempotencyHeader::keyOf($request) ?? Str::uuid()),
            ),
            tableId: new TableId($tableId),
            lines: $lines,
            idempotencyKey: IdempotencyHeader::keyOf($request),
        )));
    }
}
