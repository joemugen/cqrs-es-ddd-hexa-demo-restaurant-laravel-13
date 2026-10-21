<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\Event\DishServed;
use App\Providers\Modules\SharedKernel\Domain\AggregateRoot;
use App\Providers\Modules\SharedKernel\Domain\RecordsEvents;

final class DishAtThePass implements AggregateRoot
{
    use RecordsEvents;

    public function __construct(
        private readonly DishAtThePassId $id,
        private readonly TableId $tableId,
        private readonly DishName $dishName,
        private readonly int $quantity,
        private readonly TicketId $ticketId,
        private readonly LineId $lineId,
        private bool $served,
    ) {}

    public function serve(MenuItem $item): void
    {
        if ($this->served) {
            throw new FrontOfHouseException('That dish has already left the pass.');
        }

        $this->served = true;
        $this->recordThat(new DishServed(
            $this->tableId,
            $this->dishName,
            $this->quantity,
            $item->priceCents(),
            $item->kind(),
            $this->ticketId,
            $this->lineId,
        ));
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
