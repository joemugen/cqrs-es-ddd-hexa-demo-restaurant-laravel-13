<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Application\Command\FireDishes;
use App\Providers\BoundedContexts\Kitchen\Application\Command\Response\CourseFired;
use App\Providers\BoundedContexts\Kitchen\Domain\KitchenTicketId;
use App\Providers\BoundedContexts\Kitchen\Infrastructure\Bus\KitchenCommandBus;
use App\Providers\Modules\SharedKernel\Domain\ExpectedVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class FireDishesController
{
    public function __invoke(Request $request, string $ticketId, KitchenCommandBus $bus): JsonResponse
    {
        $validated = $request->validate([
            'course' => ['required', 'string'],
        ]);

        $fired = $bus->dispatch(new FireDishes(
            ticketId: new KitchenTicketId($ticketId),
            course: $validated['course'],
            expectedVersion: ExpectedVersion::fromIfMatch($request->header('If-Match')),
        ));

        if (! $fired instanceof CourseFired) {
            throw new LogicException('The handler of this command answers with a CourseFired.');
        }

        return response()->json($fired)->header('ETag', '"'.$fired->version.'"');
    }
}
