<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\EventStore\Domain;

interface CheckpointStore
{
    public function get(string $name): int;

    public function put(string $name, int $position): void;
}
