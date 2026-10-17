<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Query\Response;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\TableLabel;
use JsonSerializable;

final readonly class FloorPlanRow implements JsonSerializable
{
    public function __construct(
        public TableId $tableId,
        public TableLabel $label,
        public int $guestCount,
        public string $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'tableId' => (string) $this->tableId,
            'label' => (string) $this->label,
            'guestCount' => $this->guestCount,
            'status' => $this->status,
        ];
    }
}
