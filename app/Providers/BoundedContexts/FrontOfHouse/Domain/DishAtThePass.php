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
        private readonly TableOrderId $tableOrderId,
        private readonly LineId $lineId,
        private bool $served,
    ) {}

    /**
     * The guests are billed what they ordered at: the price and the kind come from their line, not from the menu of the moment.
     */
    public function serve(OrderLine $line, VisitId $visit): void
    {
        if ($this->served) {
            throw new FrontOfHouseException('That dish has already left the pass.');
        }

        $this->served = true;
        $this->recordThat(new DishServed(
            $this->tableId,
            $visit,
            $this->dishName,
            $this->quantity,
            $line->unitPriceCents,
            $line->kind,
            $this->tableOrderId,
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

    public function tableOrderId(): TableOrderId
    {
        return $this->tableOrderId;
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
