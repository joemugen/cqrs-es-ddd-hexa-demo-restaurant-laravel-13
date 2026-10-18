<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Application\Command\FireDishes;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenCommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FireDishesController
{
    public function store(Request $request, string $ticketId, KitchenCommandBus $bus): JsonResponse
    {
        $validated = $request->validate([
            'course' => ['required', 'string'],
        ]);

        return response()->json($bus->dispatch(new FireDishes(
            ticketId: new KitchenTicketId($ticketId),
            course: $validated['course'],
        )));
    }
}
