<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\TableOrderCancelled;
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
    ): self {
        return new self($id, $tableId, $visitId, $status, $reason, $unavailableLines, $requestPosition);
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

    public function ensureCanBeRevised(): void
    {
        if ($this->status === FulfilmentStatus::CLOSED) {
            throw new ObsoleteRevision;
        }

        if ($this->status !== FulfilmentStatus::AWAITING_REVISION) {
            throw new FrontOfHouseException('Only an order awaiting a revision can be revised.');
        }
    }

    public function revise(int $revisionPosition): void
    {
        $this->ensureCanBeRevised();

        $this->status = FulfilmentStatus::AWAITING_KITCHEN;
        $this->unavailableLines = [];
        $this->requestPosition = $revisionPosition;
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
     * The guests of the visit paid and left: an order that still awaits a revision will never get one.
     */
    public function close(): void
    {
        if ($this->status === FulfilmentStatus::AWAITING_REVISION) {
            $this->status = FulfilmentStatus::CLOSED;
        }
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

    public function requestPosition(): int
    {
        return $this->requestPosition;
    }

    private function isOver(): bool
    {
        return in_array($this->status, [FulfilmentStatus::CANCELLED, FulfilmentStatus::CLOSED], true);
    }
}
