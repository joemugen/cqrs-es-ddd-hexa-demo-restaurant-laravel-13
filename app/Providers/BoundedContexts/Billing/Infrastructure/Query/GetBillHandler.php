<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure\Query;

use App\Providers\BoundedContexts\Billing\Application\Query\GetBill;
use App\Providers\BoundedContexts\Billing\Application\Query\Response\BillLineView;
use App\Providers\BoundedContexts\Billing\Application\Query\Response\BillView;
use App\Providers\BoundedContexts\Billing\Application\Query\Response\PaymentView;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\BillingException;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Infrastructure\Tables;
use App\Providers\Modules\SharedKernel\Application\Bus\Attribute\AsQueryHandler;
use App\Providers\Modules\SharedKernel\Application\QueryHandler;
use Illuminate\Support\Facades\DB;

/**
 * @implements QueryHandler<GetBill, BillView>
 */
#[AsQueryHandler(GetBill::class)]
final class GetBillHandler implements QueryHandler
{
    public function __invoke(GetBill $query): BillView
    {
        $bill = DB::table(Tables::BILLS)
            ->where('cover_ref', (string) $query->coverRef)
            ->orderByDesc('sequence')
            ->orderByDesc('id')
            ->first();

        if ($bill === null) {
            throw new BillingException('No bill for this cover.', 404);
        }

        return new BillView(
            new BillId((string) $bill->id),
            new CoverRef((string) $bill->cover_ref),
            (string) $bill->status,
            (int) $bill->version,
            $this->lines($bill->id),
            (int) $bill->food_gross_cents,
            (int) $bill->alcohol_gross_cents,
            (int) $bill->vat_cents,
            (int) $bill->total_cents,
            (int) $bill->balance_cents,
            (int) $bill->tip_cents,
            $this->payments($bill->id),
        );
    }

    /**
     * @return list<BillLineView>
     */
    private function lines(mixed $billId): array
    {
        return DB::table(Tables::BILL_LINES)
            ->where('bill_id', $billId)
            ->orderBy('id')
            ->get()
            ->map(fn (object $line): BillLineView => new BillLineView(
                (string) $line->description,
                (int) $line->quantity,
                (int) $line->unit_price_cents,
                (string) $line->kind,
                (int) $line->vat_cents,
            ))
            ->all();
    }

    /**
     * @return list<PaymentView>
     */
    private function payments(mixed $billId): array
    {
        return DB::table(Tables::PAYMENTS)
            ->where('bill_id', $billId)
            ->orderBy('id')
            ->get()
            ->map(fn (object $payment): PaymentView => new PaymentView(
                (int) $payment->amount_cents,
                (int) $payment->tip_cents,
            ))
            ->all();
    }
}
