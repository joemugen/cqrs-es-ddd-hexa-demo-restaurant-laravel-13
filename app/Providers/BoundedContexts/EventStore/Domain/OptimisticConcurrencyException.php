<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Domain;

use RuntimeException;

final class OptimisticConcurrencyException extends RuntimeException
{
    public function __construct(
        public readonly string $streamId,
        public readonly int $expectedVersion,
        public readonly int $actualVersion,
    ) {
        parent::__construct(
            "Stream {$streamId} is at version {$actualVersion}, expected {$expectedVersion}.",
        );
    }
}
