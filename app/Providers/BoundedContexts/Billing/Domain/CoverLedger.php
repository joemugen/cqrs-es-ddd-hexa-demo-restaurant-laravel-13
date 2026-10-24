<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

use App\Providers\BoundedContexts\Billing\Domain\Event\BillOpened;
use App\Providers\BoundedContexts\Billing\Domain\Event\DishPlacedOnBill;
use App\Providers\BoundedContexts\Billing\Domain\Event\DishWrittenOff;
use App\Providers\Modules\SharedKernel\Domain\AggregateRoot;
use App\Providers\Modules\SharedKernel\Domain\RecordsEvents;

/**
 * What was served to a cover, and where each dish stands. A dish is on one bill only, or written off, and two roles share that
 * invariant: the ledger guarantees that a dish goes on one bill only (a guest is never charged twice), the bill decides whether it
 * is lost, once it is paid.
 *
 * The ledger does not own the bill. It decides where a dish goes from what it knows of the current bill, and says it. The bill
 * follows (a processor sends it the dish). If the bill was paid a moment before the ledger heard of it, the bill, which is
 * immutable once paid, writes the dish off itself: the loss is the rule, and the window is assumed.
 */
final class CoverLedger implements AggregateRoot
{
    use RecordsEvents;

    /**
     * @var list<string>
     */
    private array $touched = [];

    /**
     * @param  array<string, LedgerEntry>  $entries  by source key
     */
    private function __construct(
        private readonly CoverRef $coverRef,
        private array $entries,
        private ?BillId $currentBill,
        private bool $currentBillSettled,
        private readonly int $version,
    ) {}

    public static function open(CoverRef $coverRef): self
    {
        return new self($coverRef, [], null, false, 0);
    }

    /**
     * @param  list<LedgerEntry>  $entries
     */
    public static function restore(CoverRef $coverRef, array $entries, ?BillId $currentBill, bool $currentBillSettled, int $version): self
    {
        $byKey = [];

        foreach ($entries as $entry) {
            $byKey[(string) $entry->dish->sourceKey] = $entry;
        }

        return new self($coverRef, $byKey, $currentBill, $currentBillSettled, $version);
    }

    /**
     * A dish told twice is recorded once.
     */
    public function record(ServedDish $dish, int $position): void
    {
        $key = (string) $dish->sourceKey;

        if (isset($this->entries[$key])) {
            return;
        }

        $this->entries[$key] = $entry = new LedgerEntry($dish, $position);
        $this->touched[] = $key;

        if ($this->currentBill === null) {
            return;
        }

        if ($this->currentBillSettled) {
            $this->writeOffEntry($entry, $this->currentBill);

            return;
        }

        $entry->placeOn($this->currentBill);
        $this->recordThat(new DishPlacedOnBill($this->currentBill, $this->coverRef, $dish));
    }

    /**
     * Places on a new bill every dish that waits for one. Asking again while a bill is open answers with that bill.
     *
     * @return BillId|null the bill that holds the dishes, or null when nothing was served that waits for a bill
     */
    public function openBill(BillId $billId): ?BillId
    {
        if ($this->currentBill !== null && ! $this->currentBillSettled) {
            return $this->currentBill;
        }

        $waiting = array_filter($this->entries, fn (LedgerEntry $entry): bool => $entry->placement() === Placement::Unplaced);

        if ($waiting === []) {
            return null;
        }

        foreach ($waiting as $key => $entry) {
            $entry->placeOn($billId);
            $this->touched[] = $key;
        }

        $this->currentBill = $billId;
        $this->currentBillSettled = false;
        $this->recordThat(new BillOpened($billId, $this->coverRef, array_values(array_map(fn (LedgerEntry $entry): ServedDish => $entry->dish, $waiting))));

        return $billId;
    }

    /**
     * The current bill was paid: a dish served from now on is on no bill. The payment of an older bill says nothing of it.
     */
    public function settle(BillId $billId): void
    {
        if ($this->currentBill !== null && $this->currentBill->equals($billId)) {
            $this->currentBillSettled = true;
        }
    }

    public function placementOf(SourceKey $sourceKey): ?Placement
    {
        return ($this->entries[(string) $sourceKey] ?? null)?->placement();
    }

    public function id(): CoverRef
    {
        return $this->coverRef;
    }

    public function coverRef(): CoverRef
    {
        return $this->coverRef;
    }

    public function currentBill(): ?BillId
    {
        return $this->currentBill;
    }

    public function isCurrentBillSettled(): bool
    {
        return $this->currentBillSettled;
    }

    /**
     * How many times the ledger was saved when it was read: a save writes only if it is still so.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * @return list<LedgerEntry> the entries that changed since the ledger was read
     */
    public function changedEntries(): array
    {
        return array_values(array_map(
            fn (string $key): LedgerEntry => $this->entries[$key],
            array_unique($this->touched),
        ));
    }

    private function writeOffEntry(LedgerEntry $entry, BillId $billId): void
    {
        $entry->writeOff($billId);
        $this->recordThat(new DishWrittenOff($billId, $this->coverRef, $entry->dish->sourceKey, $entry->dish->description, $entry->dish->quantity, $entry->dish->grossCents()));
    }
}
