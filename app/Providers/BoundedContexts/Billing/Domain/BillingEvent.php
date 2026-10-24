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

    public const BILL_OPENED = 'billing.bill_opened';

    public const DISH_PLACED_ON_BILL = 'billing.dish_placed_on_bill';
}
