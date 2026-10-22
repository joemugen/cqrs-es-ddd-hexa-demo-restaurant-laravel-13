<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\Modules\SharedKernel\Domain\NamespacedUuid;
use App\Providers\Modules\SharedKernel\Domain\StringId;

/**
 * The guests of a table, from the moment they sit down to the moment they leave. A table sees many visits in an evening:
 * what the guests ordered, were served and owe belongs to their visit, never to the table.
 */
final readonly class VisitId extends StringId
{
    /**
     * The visit that guests start at a table under a key: the same table and key always give the same id.
     */
    public static function derivedFrom(TableId $table, string $key): self
    {
        return new self(NamespacedUuid::generate('visit', $table.':'.$key));
    }
}
