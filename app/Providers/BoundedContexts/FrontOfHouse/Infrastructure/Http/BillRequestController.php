<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http;

use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\RequestBill;
use App\Providers\BoundedContexts\FrontOfHouse\Application\Command\RequestBillHandler;
use Illuminate\Http\JsonResponse;

final class BillRequestController
{
    public function store(string $tableId, RequestBillHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new RequestBill($tableId)));
    }
}
