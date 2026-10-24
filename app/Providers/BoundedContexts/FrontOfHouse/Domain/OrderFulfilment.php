<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderCancelled;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderClosed;
use App\Providers\Modules\SharedKernel\Domain\AggregateRoot;
use App\Providers\Modules\SharedKernel\Domain\RecordsEvents;

/**
 * The state of the saga that follows an order after the dining room placed it. The kitchen cooks what it can; for the
 * lines it cannot, the order awaits a revision from the waiter, who asks the guests. A revision asks the kitchen again.
 * The guests may give up (cancelled), or pay and leave first (closed: the order belongs to their visit).
 *
 * Each answer of the kitchen names the request it answers by its position in the log, so an answer that reaches us late
 * cannot undo a revision made since.
 */
final class OrderFulfilment implements AggregateRoot
{
    use RecordsEvents;

    /**
     * @param  list<UnavailableLine>  $unavailableLines
     * @param  int  $requestPosition  where the order, or its last revision, sits in the log
     */
    private function __construct(
        private readonly TableOrderId $id,
        private readonly TableId $tableId,
        private readonly VisitId $visitId,
        private string $status,
        private ?string $reason,
        private array $unavailableLines,
        private int $requestPosition,
        private int $revisionsSeen = 0,
    ) {}

    public static function begin(TableOrderId $id, TableId $tableId, VisitId $visitId, int $requestPosition): self
    {
        return new self($id, $tableId, $visitId, FulfilmentStatus::AWAITING_KITCHEN, null, [], $requestPosition);
    }

    /**
     * @param  list<UnavailableLine>  $unavailableLines
     */
    public static function restore(
        TableOrderId $id,
        TableId $tableId,
        VisitId $visitId,
        string $status,
        ?string $reason,
        array $unavailableLines,
        int $requestPosition,
        int $revisionsSeen = 0,
    ): self {
        return new self($id, $tableId, $visitId, $status, $reason, $unavailableLines, $requestPosition, $revisionsSeen);
    }

    public function confirm(): void
    {
        if ($this->status === FulfilmentStatus::AWAITING_KITCHEN) {
            $this->status = FulfilmentStatus::CONFIRMED;
        }
    }

    /**
     * @param  list<UnavailableLine>  $lines
     * @param  int  $orderPosition  the position of the order, or revision, the kitchen answers
     */
    public function awaitRevision(array $lines, int $orderPosition): void
    {
        if ($this->isOver() || $orderPosition < $this->requestPosition) {
            return;
        }

        $this->status = FulfilmentStatus::AWAITING_REVISION;
        $this->unavailableLines = $lines;
    }

    /**
     * The order may be revised while the kitchen has said that it cannot cook some lines, and once the dining room has seen
     * the last revision that was made: two revisions cannot be in flight, since the first one is told to this saga by a fact.
     *
     * @param  int  $revisionsMade  how many times the order was revised, as the order counts them
     */
    public function ensureCanBeRevised(int $revisionsMade): void
    {
        if ($this->status === FulfilmentStatus::CLOSED) {
            throw new ObsoleteRevision;
        }

        if ($this->status !== FulfilmentStatus::AWAITING_REVISION) {
            throw new FrontOfHouseException('Only an order awaiting a revision can be revised.');
        }

        if ($revisionsMade !== $this->revisionsSeen) {
            throw new FrontOfHouseException('A revision of this order is already waiting for the kitchen.', 409);
        }
    }

    /**
     * The guests revised the order: the kitchen is asked again. A revision already seen, or one of an order that is over,
     * changes nothing.
     */
    public function revise(int $revision, int $revisionPosition): void
    {
        if ($this->isOver() || $revision <= $this->revisionsSeen) {
            return;
        }

        $this->status = FulfilmentStatus::AWAITING_KITCHEN;
        $this->unavailableLines = [];
        $this->requestPosition = $revisionPosition;
        $this->revisionsSeen = $revision;
    }

    public function cancel(string $reason): void
    {
        if (trim($reason) === '') {
            throw new FrontOfHouseException('A cancellation needs a reason.');
        }

        if ($this->status !== FulfilmentStatus::AWAITING_REVISION) {
            throw new FrontOfHouseException('Only an order awaiting a revision can be cancelled.');
        }

        $this->status = FulfilmentStatus::CANCELLED;
        $this->reason = $reason;
        $this->recordThat(new TableOrderCancelled(
            $this->id,
            $this->tableId,
            $reason,
            array_map(fn (UnavailableLine $line): LineId => $line->lineId, $this->unavailableLines),
        ));
    }

    /**
     * The guests of the visit paid and left: whatever the order was waiting for, it will not get it, and the kitchen is told to
     * stop. An order that is over says nothing again.
     */
    public function close(): void
    {
        if ($this->isOver()) {
            return;
        }

        $this->status = FulfilmentStatus::CLOSED;
        $this->recordThat(new TableOrderClosed($this->id, $this->tableId, $this->visitId));
    }

    public function id(): TableOrderId
    {
        return $this->id;
    }

    public function tableId(): TableId
    {
        return $this->tableId;
    }

    public function visitId(): VisitId
    {
        return $this->visitId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * @return list<UnavailableLine>
     */
    public function unavailableLines(): array
    {
        return $this->unavailableLines;
    }

    public function revisionsSeen(): int
    {
        return $this->revisionsSeen;
    }

    public function requestPosition(): int
    {
        return $this->requestPosition;
    }

    private function isOver(): bool
    {
        return in_array($this->status, [FulfilmentStatus::CANCELLED, FulfilmentStatus::CLOSED], true);
    }
}
