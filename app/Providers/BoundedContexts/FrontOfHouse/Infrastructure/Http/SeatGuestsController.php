<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\SeatGuests;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Bus\FrontOfHouseCommandBus;
use App\Providers\Modules\SharedKernel\Infrastructure\Http\IdempotencyHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class SeatGuestsController
{
    public function __invoke(Request $request, string $tableId, FrontOfHouseCommandBus $bus): JsonResponse
    {
        $validated = $request->validate([
            'guestCount' => ['required', 'integer'],
        ]);

        return response()->json($bus->dispatch(new SeatGuests(
            tableId: new TableId($tableId),
            visitId: VisitId::derivedFrom(new TableId($tableId), (string) (IdempotencyHeader::keyOf($request) ?? Str::uuid())),
            guestCount: (int) $validated['guestCount'],
            idempotencyKey: IdempotencyHeader::keyOf($request),
        )));
    }
}
