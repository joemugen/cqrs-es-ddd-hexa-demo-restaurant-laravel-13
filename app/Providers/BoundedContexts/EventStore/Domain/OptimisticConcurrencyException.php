<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Domain;

use App\Providers\BoundedContexts\SharedKernel\Domain\StreamId;
use RuntimeException;

final class OptimisticConcurrencyException extends RuntimeException
{
    public function __construct(
        public readonly StreamId $streamId,
        public readonly int $expectedVersion,
        public readonly int $actualVersion,
    ) {
        parent::__construct(
            "Stream {$streamId} is at version {$actualVersion}, expected {$expectedVersion}.",
        );
    }
}
