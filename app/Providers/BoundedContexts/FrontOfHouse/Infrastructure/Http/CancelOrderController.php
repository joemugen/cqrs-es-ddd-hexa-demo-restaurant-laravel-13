<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\CancelOrder;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CancelOrderController
{
    public const DEFAULT_REASON = 'The guests gave up the dishes that were unavailable.';

    public function __invoke(Request $request, string $tableOrderId, FrontOfHouseCommandBus $bus): JsonResponse
    {
        return response()->json($bus->dispatch(new CancelOrder(
            new TableOrderId($tableOrderId),
            $request->validate(['reason' => ['sometimes', 'string']])['reason'] ?? self::DEFAULT_REASON,
        )));
    }
}
