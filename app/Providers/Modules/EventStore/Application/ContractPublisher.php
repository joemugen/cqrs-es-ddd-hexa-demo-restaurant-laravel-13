<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Application;

use App\Providers\Modules\SharedKernel\Domain\TypedDomainEvent;

/**
 * Tells the other contexts what an aggregate has just decided. A fact of the domain that has a contract is translated and
 * published; one that has none stays where it was recorded. It runs in the transaction that saved the aggregate.
 */
interface ContractPublisher
{
    /**
     * @param  list<TypedDomainEvent>  $facts
     * @return list<int> where each published contract sits in the log, in the order of the facts
     */
    public function publish(array $facts): array;
}
