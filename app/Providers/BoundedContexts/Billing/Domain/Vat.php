<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Domain;

/**
 * Prices are tax-inclusive. Food eaten in is 10 percent, alcohol is 20 percent.
 */
final class Vat
{
    public static function cents(int $grossCents, string $kind): int
    {
        $rate = $kind === 'alcohol' ? 20 : 10;

        return intdiv($grossCents * $rate, 100 + $rate);
    }
}
