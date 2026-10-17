<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\BoundedContexts\Billing\Domain\BillingException;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use Illuminate\Support\Facades\DB;

final class DatabaseBillRepository implements BillRepository
{
    public function get(string $id): Bill
    {
        $row = DB::table('billing_bills')->where('id', $id)->first();

        if ($row === null) {
            throw new BillingException("Unknown bill {$id}.", 404);
        }

        return $this->bill($row);
    }

    public function save(Bill $bill): void
    {
        DB::table('billing_bills')->updateOrInsert(['id' => $bill->id()], [
            'cover_ref' => $bill->coverRef(),
            'status' => $bill->isPaid() ? 'paid' : 'open',
            'food_gross_cents' => $bill->foodGrossCents(),
            'alcohol_gross_cents' => $bill->alcoholGrossCents(),
            'vat_cents' => $bill->vatCents(),
            'total_cents' => $bill->totalCents(),
            'balance_cents' => $bill->balanceCents(),
            'tip_cents' => $bill->tipCents(),
        ]);

        DB::table('billing_bill_lines')->where('bill_id', $bill->id())->delete();

        foreach ($bill->lines() as $line) {
            DB::table('billing_bill_lines')->insert([
                'bill_id' => $bill->id(),
                'source_key' => $line->sourceKey,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unitPriceCents,
                'kind' => $line->kind,
                'vat_cents' => $line->vatCents(),
            ]);
        }

        DB::table('billing_payments')->where('bill_id', $bill->id())->delete();

        foreach ($bill->payments() as $payment) {
            DB::table('billing_payments')->insert([
                'bill_id' => $bill->id(),
                'amount_cents' => $payment['amountCents'],
                'tip_cents' => $payment['tipCents'],
            ]);
        }
    }

    public function openFor(string $coverRef): ?Bill
    {
        $row = DB::table('billing_bills')
            ->where('cover_ref', $coverRef)
            ->where('status', 'open')
            ->first();

        return $row === null ? null : $this->bill($row);
    }

    private function bill(object $row): Bill
    {
        $lines = DB::table('billing_bill_lines')
            ->where('bill_id', $row->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $line): ServedDish => new ServedDish(
                (string) $line->source_key,
                (string) $line->description,
                (int) $line->quantity,
                (int) $line->unit_price_cents,
                (string) $line->kind,
            ))
            ->all();

        $payments = DB::table('billing_payments')
            ->where('bill_id', $row->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $payment): array => [
                'amountCents' => (int) $payment->amount_cents,
                'tipCents' => (int) $payment->tip_cents,
            ])
            ->all();

        return Bill::restore(
            (string) $row->id,
            (string) $row->cover_ref,
            $lines,
            $payments,
            $row->status === 'paid',
        );
    }
}
