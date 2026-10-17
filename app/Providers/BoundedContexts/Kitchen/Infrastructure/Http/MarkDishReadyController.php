<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Application\Command\MarkDishReady;
use App\Providers\BoundedContexts\Kitchen\Application\Command\MarkDishReadyHandler;
use Illuminate\Http\JsonResponse;

final class MarkDishReadyController
{
    public function store(string $ticketId, string $lineId, MarkDishReadyHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new MarkDishReady($ticketId, $lineId)));
    }
}
