<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\BillRequested;
use App\Providers\Modules\SharedKernel\Domain\AggregateRoot;
use App\Providers\Modules\SharedKernel\Domain\RecordsEvents;

final class Table implements AggregateRoot
{
    use RecordsEvents;

    private function __construct(
        private readonly TableId $id,
        private readonly TableLabel $label,
        private int $guestCount,
        private ?VisitId $visit,
    ) {}

    public static function restore(TableId $id, TableLabel $label, int $guestCount, ?VisitId $visit = null): self
    {
        return new self($id, $label, $guestCount, $visit);
    }

    public function seat(VisitId $visit, int $guestCount): void
    {
        if ($guestCount < 1) {
            throw new FrontOfHouseException('Seat at least one guest.');
        }

        if ($this->guestCount > 0) {
            throw new FrontOfHouseException('This table already has guests.');
        }

        $this->guestCount = $guestCount;
        $this->visit = $visit;
    }

    public function requestBill(): void
    {
        if (! $this->hasGuests()) {
            throw new FrontOfHouseException('This table has no guests to bill.');
        }

        $this->recordThat(new BillRequested($this->id, $this->visit));
    }

    /**
     * The guests of a visit are gone. A payment the table hears late, or twice, says nothing of the guests who sit there
     * now.
     */
    public function free(VisitId $visit): void
    {
        if (! $this->isHosting($visit)) {
            return;
        }

        $this->guestCount = 0;
        $this->visit = null;
    }

    public function isHosting(VisitId $visit): bool
    {
        return $this->visit !== null && $this->visit->equals($visit);
    }

    public function visit(): ?VisitId
    {
        return $this->visit;
    }

    public function hasGuests(): bool
    {
        return $this->guestCount > 0;
    }

    public function id(): TableId
    {
        return $this->id;
    }

    public function label(): TableLabel
    {
        return $this->label;
    }

    public function guestCount(): int
    {
        return $this->guestCount;
    }

    public function status(): string
    {
        return $this->hasGuests() ? TableStatus::OCCUPIED : TableStatus::FREE;
    }
}
