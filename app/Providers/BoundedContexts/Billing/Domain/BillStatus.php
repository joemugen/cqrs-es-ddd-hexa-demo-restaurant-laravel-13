<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

/**
 * Where a bill stands: open until the balance is gone, then paid and immutable.
 */
final class BillStatus
{
    public const OPEN = 'open';

    public const PAID = 'paid';
}
