<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Http;

use App\Providers\BoundedContexts\Billing\Application\Query\GetBill;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Infrastructure\Bus\BillingQueryBus;
use Illuminate\Http\JsonResponse;

final class BillController
{
    public function show(string $coverRef, BillingQueryBus $bus): JsonResponse
    {
        return response()->json($bus->ask(new GetBill(new CoverRef($coverRef))));
    }
}
