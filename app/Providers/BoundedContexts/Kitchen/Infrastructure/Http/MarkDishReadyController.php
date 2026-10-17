<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Application\Command\MarkDishReady;
use App\Providers\BoundedContexts\Kitchen\Application\Command\MarkDishReadyHandler;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use Illuminate\Http\JsonResponse;

final class MarkDishReadyController
{
    public function store(string $ticketId, string $lineId, MarkDishReadyHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new MarkDishReady(new KitchenTicketId($ticketId), new LineId($lineId))));
    }
}
