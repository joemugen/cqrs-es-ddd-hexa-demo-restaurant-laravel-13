<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\Billing\Application;

use App\Providers\BoundedContexts\Billing\Domain\ServedDish;

interface ServedLedger
{
    public function record(string $coverRef, ServedDish $dish): void;

    /**
     * @return list<ServedDish>
     */
    public function unbilled(string $coverRef): array;

    /**
     * @param  list<string>  $sourceKeys
     */
    public function attach(string $billId, array $sourceKeys): void;
}
