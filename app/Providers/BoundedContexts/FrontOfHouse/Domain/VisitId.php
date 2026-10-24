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
     * The visit of the nth guests a table seats: it comes from the table and from how many visits it has seated, not from
     * what a client sent, so no key that is purged or replayed can name a visit twice.
     */
    public static function forSeating(TableId $table, int $ordinal): self
    {
        return new self(NamespacedUuid::generate('seating', $table.':'.$ordinal));
    }
}
