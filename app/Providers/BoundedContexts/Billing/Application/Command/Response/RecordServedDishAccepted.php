<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application\Command\Response;

use App\Providers\BoundedContexts\Billing\Domain\SourceKey;
use App\Providers\Modules\SharedKernel\Application\CommandResponse;

final readonly class RecordServedDishAccepted implements CommandResponse
{
    public function __construct(public SourceKey $sourceKey) {}

    public function jsonSerialize(): array
    {
        return [
            'sourceKey' => (string) $this->sourceKey,
            'accepted' => true,
        ];
    }
}
