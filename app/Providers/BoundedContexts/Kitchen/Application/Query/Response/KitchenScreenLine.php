<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Query\Response;

use App\Providers\BoundedContexts\Kitchen\Domain\DishName;
use App\Providers\BoundedContexts\Kitchen\Domain\LineId;
use JsonSerializable;

final readonly class KitchenScreenLine implements JsonSerializable
{
    /**
     * @param  list<string>  $allergens
     */
    public function __construct(
        public LineId $lineId,
        public DishName $dishName,
        public string $station,
        public int $cookingMinutes,
        public int $quantity,
        public string $course,
        public array $allergens,
        public string $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'lineId' => (string) $this->lineId,
            'dishName' => (string) $this->dishName,
            'station' => $this->station,
            'cookingMinutes' => $this->cookingMinutes,
            'quantity' => $this->quantity,
            'course' => $this->course,
            'allergens' => $this->allergens,
            'status' => $this->status,
        ];
    }
}
