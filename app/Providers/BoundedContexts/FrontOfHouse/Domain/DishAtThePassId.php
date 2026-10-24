<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Domain;

use App\Providers\Modules\SharedKernel\Domain\NamespacedUuid;
use App\Providers\Modules\SharedKernel\Domain\StringId;

final readonly class DishAtThePassId extends StringId
{
    /**
     * The dish a line of an order puts at the pass: the same order and line always give the same id.
     */
    public static function forLine(TableOrderId $order, LineId $line): self
    {
        return new self(NamespacedUuid::generate('pass', $order.':'.$line));
    }
}
