<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Application\Command\MarkDishReady;
use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\DishMarkedReady;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenCommandBus;
use App\Providers\Modules\SharedKernel\Domain\ExpectedVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class MarkDishReadyController
{
    public function __invoke(Request $request, string $ticketId, string $lineId, KitchenCommandBus $bus): JsonResponse
    {
        $ready = $bus->dispatch(new MarkDishReady(
            new KitchenTicketId($ticketId),
            new LineId($lineId),
            ExpectedVersion::fromIfMatch($request->header('If-Match')),
        ));

        if (! $ready instanceof DishMarkedReady) {
            throw new LogicException('The handler of this command answers with a DishMarkedReady.');
        }

        return response()->json($ready)->header('ETag', '"'.$ready->version.'"');
    }
}
