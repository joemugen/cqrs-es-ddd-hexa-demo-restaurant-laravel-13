<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishName;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\LineId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableOrderId;
use JsonSerializable;

final readonly class ToServeEntry implements JsonSerializable
{
    public function __construct(
        public DishAtThePassId $id,
        public TableId $tableId,
        public TableLabel $tableLabel,
        public DishName $dishName,
        public int $quantity,
        public TableOrderId $tableOrderId,
        public LineId $lineId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => (string) $this->id,
            'tableId' => (string) $this->tableId,
            'tableLabel' => (string) $this->tableLabel,
            'dishName' => (string) $this->dishName,
            'quantity' => $this->quantity,
            'tableOrderId' => (string) $this->tableOrderId,
            'lineId' => (string) $this->lineId,
        ];
    }
}
