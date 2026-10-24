<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Application\Command\Response;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\VisitId;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;

final readonly class FreeTableAccepted implements CommandResponse
{
    public function __construct(public VisitId $visitId) {}

    public function jsonSerialize(): array
    {
        return [
            'visitId' => (string) $this->visitId,
            'accepted' => true,
        ];
    }
}
