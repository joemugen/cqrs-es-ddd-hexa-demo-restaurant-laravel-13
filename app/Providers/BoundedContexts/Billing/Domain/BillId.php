<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

use App\Providers\Modules\SharedKernel\Domain\NamespacedUuid;
use App\Providers\Modules\SharedKernel\Domain\StringId;

final readonly class BillId extends StringId
{
    /**
     * The bill asked for by the event at $position: a replay of that event finds the same id.
     */
    public static function forBillRequest(CoverRef $cover, int $position): self
    {
        return new self(NamespacedUuid::generate('bill', $cover.':'.$position));
    }
}
