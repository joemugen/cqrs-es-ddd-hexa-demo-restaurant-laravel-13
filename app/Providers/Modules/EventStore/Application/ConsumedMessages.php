<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Application;

use App\Providers\Modules\EventStore\Domain\StoredEvent;
use App\Providers\Modules\SharedKernel\Application\Publication\ConsumedMessage;

/**
 * What a consumer reads of a fact in the log: the message its own context declared for that contract, or nothing.
 */
interface ConsumedMessages
{
    /**
     * @param  class-string  $consumer  the processor or the projector that reads: its context is the one that declared
     *                                  the messages
     */
    public function read(StoredEvent $event, string $consumer): ?ConsumedMessage;
}
