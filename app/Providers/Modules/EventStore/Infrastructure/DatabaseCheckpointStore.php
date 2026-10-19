<?php

declare(strict_types=1);

namespace App\Providers\Modules\EventStore\Infrastructure;

use App\Providers\Modules\EventStore\Domain\CheckpointStore;
use Illuminate\Support\Facades\DB;

final class DatabaseCheckpointStore implements CheckpointStore
{
    public function get(string $name): int
    {
        $position = DB::table((string) config('event_store.checkpoints_table'))
            ->where('name', $name)
            ->value('position');

        return $position === null ? 0 : (int) $position;
    }

    public function put(string $name, int $position): void
    {
        $table = (string) config('event_store.checkpoints_table');

        $updated = DB::table($table)->where('name', $name)->update([
            'position' => $position,
            'updated_at' => now(),
        ]);

        if ($updated === 0 && ! DB::table($table)->where('name', $name)->exists()) {
            DB::table($table)->insert([
                'name' => $name,
                'position' => $position,
                'updated_at' => now(),
            ]);
        }
    }
}
