<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\Modules\SharedKernel\Domain\NamespacedUuid;
use App\Providers\Modules\SharedKernel\Domain\StringId;

final readonly class TableOrderId extends StringId
{
    /**
     * The order a table places under an idempotency key: the same table and key always give the same id.
     */
    public static function derivedFrom(TableId $table, string $idempotencyKey): self
    {
        return new self(NamespacedUuid::generate('table-order', $table.':'.$idempotencyKey));
    }
}
