<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Infrastructure;

use App\Providers\Modules\SharedKernel\Application\Metrics\ConsumerLag;
use Illuminate\Support\Facades\DB;

final class DatabaseConsumerLag implements ConsumerLag
{
    public function byCheckpoint(): array
    {
        $last = (int) DB::table((string) config('event_store.events_table'))->max('position');

        return DB::table((string) config('event_store.checkpoints_table'))
            ->orderBy('name')
            ->pluck('position', 'name')
            ->map(fn (int|string $position): int => max($last - (int) $position, 0))
            ->all();
    }
}
