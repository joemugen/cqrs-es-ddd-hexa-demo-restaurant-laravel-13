<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Domain;

interface CheckpointStore
{
    public function get(string $name): int;

    public function put(string $name, int $position): void;
}
