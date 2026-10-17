<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Http;

use App\Providers\BoundedContexts\Billing\Infrastructure\Queries\BillQuery;
use Illuminate\Http\JsonResponse;

final class BillController
{
    public function show(string $coverRef, BillQuery $query): JsonResponse
    {
        return response()->json($query->forCover($coverRef));
    }
}
