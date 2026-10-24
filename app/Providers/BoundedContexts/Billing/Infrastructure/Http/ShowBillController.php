<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Http;

use App\Providers\BoundedContexts\Billing\Application\Query\GetBill;
use App\Providers\BoundedContexts\Billing\Application\Query\Response\BillView;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Infrastructure\Bus\BillingQueryBus;
use Illuminate\Http\JsonResponse;
use LogicException;

final class ShowBillController
{
    public function __invoke(string $coverRef, BillingQueryBus $bus): JsonResponse
    {
        $bill = $bus->ask(new GetBill(new CoverRef($coverRef)));

        if (! $bill instanceof BillView) {
            throw new LogicException('The handler of this query answers with a BillView.');
        }

        return response()->json($bill)->header('ETag', '"'.$bill->version.'"');
    }
}
