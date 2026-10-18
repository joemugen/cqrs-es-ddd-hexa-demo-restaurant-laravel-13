<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Http;

use App\Providers\BoundedContexts\Billing\Application\Command\PayBill;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Infrastructure\Bus\BillingCommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController
{
    public function store(Request $request, string $billId, BillingCommandBus $bus): JsonResponse
    {
        $validated = $request->validate([
            'amountCents' => ['required', 'integer'],
            'tipCents' => ['sometimes', 'integer'],
        ]);

        return response()->json($bus->dispatch(new PayBill(
            billId: new BillId($billId),
            amountCents: (int) $validated['amountCents'],
            tipCents: (int) ($validated['tipCents'] ?? 0),
        )));
    }
}
