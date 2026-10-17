<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Application\FireDishes;
use App\Providers\BoundedContexts\Kitchen\Application\FireDishesHandler;
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
            ticketId: $ticketId,
            course: $validated['course'],
        )));
    }
}
