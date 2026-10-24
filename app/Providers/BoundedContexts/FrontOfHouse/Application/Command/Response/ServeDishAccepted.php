<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\DishAtThePassId;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;

final readonly class ServeDishAccepted implements CommandResponse
{
    public function __construct(public DishAtThePassId $toServeId) {}

    public function jsonSerialize(): array
    {
        return [
            'toServeId' => (string) $this->toServeId,
            'accepted' => true,
        ];
    }
}
