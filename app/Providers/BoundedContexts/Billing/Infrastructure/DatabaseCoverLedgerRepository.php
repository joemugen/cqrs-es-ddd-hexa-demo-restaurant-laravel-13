<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure;

use App\Providers\BoundedContexts\Billing\Application\CoverLedgerRepository;
use App\Providers\BoundedContexts\Billing\Domain\BillId;
use App\Providers\BoundedContexts\Billing\Domain\CoverLedger;
use App\Providers\BoundedContexts\Billing\Domain\CoverRef;
use App\Providers\BoundedContexts\Billing\Domain\LedgerEntry;
use App\Providers\BoundedContexts\Billing\Domain\Placement;
use App\Providers\BoundedContexts\Billing\Domain\ServedDish;
use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\Modules\SharedKernel\Application\Concurrency\ConcurrencyConflict;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DatabaseCoverLedgerRepository implements CoverLedgerRepository
{
    public function get(CoverRef $coverRef): CoverLedger
    {
        $header = DB::table(Tables::COVER_LEDGERS)->where('cover_ref', (string) $coverRef)->first();

        $entries = DB::table(Tables::SERVED_ITEMS)
            ->where('cover_ref', (string) $coverRef)
            ->orderBy('position')
            ->orderBy('source_key')
            ->get()
            ->map(fn (object $row): LedgerEntry => $this->entry($row))
            ->all();

        if ($header === null) {
            return $entries === [] ? CoverLedger::open($coverRef) : CoverLedger::restore($coverRef, $entries, null, false, 0);
        }

        return CoverLedger::restore(
            $coverRef,
            $entries,
            $header->current_bill_id !== null ? new BillId((string) $header->current_bill_id) : null,
            (bool) $header->current_bill_settled,
            (int) $header->version,
        );
    }

    /**
     * The ledger is written only if nobody saved it since it was read, whoever they are: the dishes that were served, the bill that
     * was asked for, the payment that was heard.
     */
    public function save(CoverLedger $ledger): void
    {
        $this->writeHeader($ledger);

        foreach ($ledger->changedEntries() as $entry) {
            $this->writeEntry($ledger->coverRef(), $entry);
        }
    }

    private function writeHeader(CoverLedger $ledger): void
    {
        $row = [
            'current_bill_id' => $ledger->currentBill() === null ? null : (string) $ledger->currentBill(),
            'current_bill_settled' => $ledger->isCurrentBillSettled(),
            'version' => $ledger->version() + 1,
        ];

        $written = DB::table(Tables::COVER_LEDGERS)
            ->where('cover_ref', (string) $ledger->coverRef())
            ->where('version', $ledger->version())
            ->update($row);

        if ($written > 0) {
            return;
        }

        if (DB::table(Tables::COVER_LEDGERS)->where('cover_ref', (string) $ledger->coverRef())->exists()) {
            throw new ConcurrencyConflict("The ledger of the cover {$ledger->coverRef()} was saved since it was read.");
        }

        try {
            DB::table(Tables::COVER_LEDGERS)->insert(['cover_ref' => (string) $ledger->coverRef()] + $row);
        } catch (UniqueConstraintViolationException) {
            throw new ConcurrencyConflict("The ledger of the cover {$ledger->coverRef()} was saved since it was read.");
        }
    }

    private function writeEntry(CoverRef $coverRef, LedgerEntry $entry): void
    {
        $key = (string) $entry->dish->sourceKey;

        DB::table(Tables::SERVED_ITEMS)->insertOrIgnore([
            'source_key' => $key,
            'cover_ref' => (string) $coverRef,
            'description' => $entry->dish->description,
            'quantity' => $entry->dish->quantity,
            'unit_price_cents' => $entry->dish->unitPriceCents,
            'kind' => $entry->dish->kind,
            'position' => $entry->position,
        ]);

        DB::table(Tables::SERVED_ITEMS)->where('source_key', $key)->update([
            'bill_id' => $entry->billId() === null ? null : (string) $entry->billId(),
            'written_off_at' => $entry->placement() === Placement::WrittenOff ? now() : null,
        ]);
    }

    private function entry(object $row): LedgerEntry
    {
        return new LedgerEntry(
            new ServedDish(
                new SourceKey((string) $row->source_key),
                (string) $row->description,
                (int) $row->quantity,
                (int) $row->unit_price_cents,
                (string) $row->kind,
            ),
            (int) $row->position,
            $row->bill_id !== null ? new BillId((string) $row->bill_id) : null,
            $row->written_off_at !== null,
        );
    }
}
