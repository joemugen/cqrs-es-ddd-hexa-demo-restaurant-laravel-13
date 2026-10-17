<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Http;

use App\Providers\BoundedContexts\Billing\Application\PayBill;
use App\Providers\BoundedContexts\Billing\Application\PayBillHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController
{
    public function store(Request $request, string $billId, PayBillHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'amountCents' => ['required', 'integer'],
            'tipCents' => ['sometimes', 'integer'],
        ]);

        return response()->json($handler->handle(new PayBill(
            billId: $billId,
            amountCents: (int) $validated['amountCents'],
            tipCents: (int) ($validated['tipCents'] ?? 0),
        )));
    }
}
