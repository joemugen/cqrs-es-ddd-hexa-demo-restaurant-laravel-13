<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\SeatGuests;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\SeatGuestsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GuestController
{
    public function store(Request $request, string $tableId, SeatGuestsHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'guestCount' => ['required', 'integer'],
        ]);

        return response()->json($handler->handle(new SeatGuests(
            tableId: $tableId,
            guestCount: (int) $validated['guestCount'],
        )));
    }
}
