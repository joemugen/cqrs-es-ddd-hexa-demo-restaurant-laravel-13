<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

/**
 * A dish served to a cover, and where it stands. It leaves "unplaced" once, for a bill or for the loss: nothing else moves it.
 */
final class LedgerEntry
{
    public function __construct(
        public readonly ServedDish $dish,
        public readonly int $position,
        private ?BillId $billId = null,
        private bool $writtenOff = false,
    ) {}

    public function placement(): Placement
    {
        return match (true) {
            $this->writtenOff => Placement::WrittenOff,
            $this->billId !== null => Placement::OnBill,
            default => Placement::Unplaced,
        };
    }

    public function billId(): ?BillId
    {
        return $this->billId;
    }

    public function placeOn(BillId $billId): void
    {
        $this->billId = $billId;
    }

    public function writeOff(?BillId $billId = null): void
    {
        $this->billId ??= $billId;
        $this->writtenOff = true;
    }
}
