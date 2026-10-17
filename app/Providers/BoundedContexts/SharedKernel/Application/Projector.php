<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\SharedKernel\Application;

/**
 * Folds the event log into a read model from its checkpoint. It decides nothing.
 */
interface Projector
{
    /**
     * @return int the number of events that changed the read model
     */
    public function catchUp(): int;
}
