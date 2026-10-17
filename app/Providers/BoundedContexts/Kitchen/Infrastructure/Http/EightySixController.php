<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Infrastructure\Http;

use App\Providers\BoundedContexts\Kitchen\Application\DeclareDishEightySixed;
use App\Providers\BoundedContexts\Kitchen\Application\DeclareDishEightySixedHandler;
use Illuminate\Http\JsonResponse;

final class EightySixController
{
    public function store(string $dishId, DeclareDishEightySixedHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new DeclareDishEightySixed($dishId)));
    }
}
