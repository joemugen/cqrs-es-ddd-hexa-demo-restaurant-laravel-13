<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure;

use App\Providers\BoundedContexts\Billing\Application\BillRepository;
use App\Providers\BoundedContexts\Billing\Domain\Bill;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\BillingException;
use App\Providers\BoundedContexts\Billing\Domain\BillStatus;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\Payment;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use Illuminate\Support\Facades\DB;

final class DatabaseBillRepository implements BillRepository
{
    public function get(BillId $id): Bill
    {
        $row = DB::table(Tables::BILLS)->where('id', (string) $id)->first();

        if ($row === null) {
            throw new BillingException('Unknown bill :id.', 404, ['id' => $id]);
        }

        return $this->bill($row);
    }

    public function save(Bill $bill): void
    {
        DB::table(Tables::BILLS)->updateOrInsert(['id' => (string) $bill->id()], [
            'cover_ref' => (string) $bill->coverRef(),
            'status' => $bill->isPaid() ? BillStatus::PAID : BillStatus::OPEN,
            'food_gross_cents' => $bill->foodGrossCents(),
            'alcohol_gross_cents' => $bill->alcoholGrossCents(),
            'vat_cents' => $bill->vatCents(),
            'total_cents' => $bill->totalCents(),
            'balance_cents' => $bill->balanceCents(),
            'tip_cents' => $bill->tipCents(),
        ]);

        DB::table(Tables::BILL_LINES)->where('bill_id', (string) $bill->id())->delete();

        foreach ($bill->lines() as $line) {
            DB::table(Tables::BILL_LINES)->insert([
                'bill_id' => (string) $bill->id(),
                'source_key' => (string) $line->sourceKey,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unitPriceCents,
                'kind' => $line->kind,
                'vat_cents' => $line->vatCents(),
            ]);
        }

        DB::table(Tables::PAYMENTS)->where('bill_id', (string) $bill->id())->delete();

        foreach ($bill->payments() as $payment) {
            DB::table(Tables::PAYMENTS)->insert([
                'bill_id' => (string) $bill->id(),
                'amount_cents' => $payment->amountCents,
                'tip_cents' => $payment->tipCents,
            ]);
        }
    }

    public function openFor(CoverRef $coverRef): ?Bill
    {
        $row = DB::table(Tables::BILLS)
            ->where('cover_ref', (string) $coverRef)
            ->where('status', BillStatus::OPEN)
            ->first();

        return $row === null ? null : $this->bill($row);
    }

    private function bill(object $row): Bill
    {
        $lines = DB::table(Tables::BILL_LINES)
            ->where('bill_id', $row->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $line): ServedDish => new ServedDish(
                new SourceKey((string) $line->source_key),
                (string) $line->description,
                (int) $line->quantity,
                (int) $line->unit_price_cents,
                (string) $line->kind,
            ))
            ->all();

        $payments = DB::table(Tables::PAYMENTS)
            ->where('bill_id', $row->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $payment): Payment => new Payment(
                (int) $payment->amount_cents,
                (int) $payment->tip_cents,
            ))
            ->all();

        return Bill::restore(
            new BillId((string) $row->id),
            new CoverRef((string) $row->cover_ref),
            $lines,
            $payments,
            $row->status === BillStatus::PAID,
        );
    }
}
