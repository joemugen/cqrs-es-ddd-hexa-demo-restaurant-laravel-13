<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Queries;

use App\Providers\BoundedContexts\Billing\Domain\BillingException;
use Illuminate\Support\Facades\DB;

final class BillQuery
{
    /**
     * @return array<string, mixed>
     */
    public function forCover(string $coverRef): array
    {
        $bill = DB::table('billing_bills')
            ->where('cover_ref', $coverRef)
            ->orderByDesc('id')
            ->first();

        if ($bill === null) {
            throw new BillingException('No bill for this cover.', 404);
        }

        $lines = DB::table('billing_bill_lines')
            ->where('bill_id', $bill->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $line): array => [
                'description' => (string) $line->description,
                'quantity' => (int) $line->quantity,
                'unitPriceCents' => (int) $line->unit_price_cents,
                'kind' => (string) $line->kind,
                'vatCents' => (int) $line->vat_cents,
            ])
            ->all();

        $payments = DB::table('billing_payments')
            ->where('bill_id', $bill->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $payment): array => [
                'amountCents' => (int) $payment->amount_cents,
                'tipCents' => (int) $payment->tip_cents,
            ])
            ->all();

        return [
            'billId' => (string) $bill->id,
            'coverRef' => (string) $bill->cover_ref,
            'status' => (string) $bill->status,
            'lines' => $lines,
            'foodGrossCents' => (int) $bill->food_gross_cents,
            'alcoholGrossCents' => (int) $bill->alcohol_gross_cents,
            'vatCents' => (int) $bill->vat_cents,
            'totalCents' => (int) $bill->total_cents,
            'balanceCents' => (int) $bill->balance_cents,
            'tipCents' => (int) $bill->tip_cents,
            'payments' => $payments,
        ];
    }
}
