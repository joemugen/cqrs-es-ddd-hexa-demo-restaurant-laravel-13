<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Kitchen\Application\Command\Response;

use App\Providers\BoundedContexts\Kitchen\Domain\DishId;
use App\Providers\BoundedContexts\SharedKernel\Application\CommandResponse;

final readonly class DishEightySixed implements CommandResponse
{
    public function __construct(public DishId $dishId) {}

    public function jsonSerialize(): array
    {
        return [
            'dishId' => (string) $this->dishId,
            'accepted' => true,
        ];
    }
}
