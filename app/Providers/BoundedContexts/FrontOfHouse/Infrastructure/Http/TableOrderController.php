<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\PlaceTableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\PlaceTableOrderHandler;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TableOrderController
{
    public function store(Request $request, string $tableId, PlaceTableOrderHandler $handler): JsonResponse
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

        return response()->json($handler->handle(new PlaceTableOrder(
            tableOrderId: new TableOrderId((string) Str::ulid()),
            tableId: new TableId($tableId),
            lines: $lines,
        )));
    }
}
