<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\PlaceTableOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\PlaceTableOrderHandler;
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

        $lines = array_map(fn (array $line): array => [
            'menuItemId' => $line['menuItemId'],
            'quantity' => (int) $line['quantity'],
            'course' => $line['course'],
        ], $validated['lines']);

        return response()->json($handler->handle(new PlaceTableOrder(
            tableOrderId: (string) Str::ulid(),
            tableId: $tableId,
            lines: $lines,
        )));
    }
}
