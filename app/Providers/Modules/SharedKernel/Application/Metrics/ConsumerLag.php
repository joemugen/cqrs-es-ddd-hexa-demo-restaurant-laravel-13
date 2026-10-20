<?php

declare(strict_types=1);

namespace App\Providers\Modules\SharedKernel\Application\Metrics;

interface ConsumerLag
{
    /**
     * How many positions of the log each checkpoint has left to read, by checkpoint name.
     *
     * @return array<string, int>
     */
    public function byCheckpoint(): array;
}
