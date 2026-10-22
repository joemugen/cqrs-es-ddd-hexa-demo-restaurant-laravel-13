<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

/**
 * The facts the till publishes. Other contexts read these names; do not rename one lightly.
 */
final class BillingEvent
{
    public const BILL_PAID = 'billing.bill_paid';

    public const DISH_WRITTEN_OFF = 'billing.dish_written_off';
}
