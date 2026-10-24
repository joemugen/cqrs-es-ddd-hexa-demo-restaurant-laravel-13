<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

/**
 * Where a dish that was served stands for the till: it waits for a bill, it is on one, or nobody can be charged for it.
 */
enum Placement: string
{
    case Unplaced = 'unplaced';
    case OnBill = 'on_bill';
    case WrittenOff = 'written_off';
}
