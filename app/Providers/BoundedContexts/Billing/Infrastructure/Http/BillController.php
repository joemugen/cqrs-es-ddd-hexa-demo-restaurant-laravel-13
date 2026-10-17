<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Http;

use App\Providers\BoundedContexts\Billing\Application\Query\GetBill;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Infrastructure\Query\GetBillHandler;
use Illuminate\Http\JsonResponse;

final class BillController
{
    public function show(string $coverRef, GetBillHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new GetBill(new CoverRef($coverRef))));
    }
}
