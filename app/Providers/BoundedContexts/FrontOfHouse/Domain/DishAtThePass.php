<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\BoundedContexts\SharedKernel\Domain\AggregateRoot;

final class DishAtThePass implements AggregateRoot
{
    public function __construct(
        private readonly DishAtThePassId $id,
        private readonly TableId $tableId,
        private readonly DishName $dishName,
        private readonly int $quantity,
        private readonly TicketId $ticketId,
        private readonly LineId $lineId,
        private bool $served,
    ) {}

    public function serve(): void
    {
        if ($this->served) {
            throw new FrontOfHouseException('That dish has already left the pass.');
        }

        $this->served = true;
    }

    public function id(): DishAtThePassId
    {
        return $this->id;
    }

    public function tableId(): TableId
    {
        return $this->tableId;
    }

    public function dishName(): DishName
    {
        return $this->dishName;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function ticketId(): TicketId
    {
        return $this->ticketId;
    }

    public function lineId(): LineId
    {
        return $this->lineId;
    }

    public function isServed(): bool
    {
        return $this->served;
    }
}
