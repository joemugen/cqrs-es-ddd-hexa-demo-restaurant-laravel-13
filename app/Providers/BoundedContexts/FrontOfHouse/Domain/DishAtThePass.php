<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\BoundedContexts\SharedKernel\Domain\AggregateRoot;

final class DishAtThePass implements AggregateRoot
{
    public function __construct(
        private readonly string $id,
        private readonly string $tableId,
        private readonly string $dishName,
        private readonly int $quantity,
        private readonly string $ticketId,
        private readonly string $lineId,
        private bool $served,
    ) {}

    public function serve(): void
    {
        if ($this->served) {
            throw new FrontOfHouseException('That dish has already left the pass.');
        }

        $this->served = true;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function tableId(): string
    {
        return $this->tableId;
    }

    public function dishName(): string
    {
        return $this->dishName;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function ticketId(): string
    {
        return $this->ticketId;
    }

    public function lineId(): string
    {
        return $this->lineId;
    }

    public function isServed(): bool
    {
        return $this->served;
    }
}
