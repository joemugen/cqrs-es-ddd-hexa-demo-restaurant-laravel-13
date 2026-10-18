<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Infrastructure;

/**
 * Table names. Migrations keep the literal name on purpose: a migration describes the schema as it was, and must not move when a constant is renamed.
 */
final class Tables
{
    public const BILLS = 'billing_bills';

    public const BILL_LINES = 'billing_bill_lines';

    public const PAYMENTS = 'billing_payments';

    public const SERVED_ITEMS = 'billing_served_items';
}
