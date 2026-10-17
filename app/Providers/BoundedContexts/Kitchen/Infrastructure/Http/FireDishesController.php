<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Application\Command\FireDishes;
use App\Providers\BoundedContexts\Kitchen\Application\Command\FireDishesHandler;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FireDishesController
{
    public function store(Request $request, string $ticketId, FireDishesHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'course' => ['required', 'string'],
        ]);

        return response()->json($handler->handle(new FireDishes(
            ticketId: new KitchenTicketId($ticketId),
            course: $validated['course'],
        )));
    }
}
