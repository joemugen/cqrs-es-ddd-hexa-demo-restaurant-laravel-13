<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\Modules\SharedKernel\Domain\AggregateRoot;

/**
 * The state of the saga that follows an order after the dining room placed it: the kitchen opens a ticket and the
 * order is confirmed, or the kitchen refuses it and the order is cancelled, which is the compensation.
 */
final class OrderFulfilment implements AggregateRoot
{
    private function __construct(
        private readonly TableOrderId $id,
        private readonly TableId $tableId,
        private string $status,
        private ?string $reason,
    ) {}

    public static function begin(TableOrderId $id, TableId $tableId): self
    {
        return new self($id, $tableId, FulfilmentStatus::AWAITING_KITCHEN, null);
    }

    public static function restore(TableOrderId $id, TableId $tableId, string $status, ?string $reason): self
    {
        return new self($id, $tableId, $status, $reason);
    }

    public function confirm(): void
    {
        if ($this->status === FulfilmentStatus::CANCELLED) {
            throw new FrontOfHouseException('A cancelled order cannot be confirmed.');
        }

        $this->status = FulfilmentStatus::CONFIRMED;
    }

    public function cancel(string $reason): void
    {
        if (trim($reason) === '') {
            throw new FrontOfHouseException('A cancellation needs a reason.');
        }

        if ($this->status === FulfilmentStatus::CONFIRMED) {
            throw new FrontOfHouseException('A confirmed order cannot be cancelled.');
        }

        $this->status = FulfilmentStatus::CANCELLED;
        $this->reason = $reason;
    }

    public function id(): TableOrderId
    {
        return $this->id;
    }

    public function tableId(): TableId
    {
        return $this->tableId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
