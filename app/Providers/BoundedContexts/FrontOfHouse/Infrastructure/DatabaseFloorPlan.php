<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use App\Providers\BoundedContexts\FrontOfHouse\Application\FloorPlan;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\Table;
use Illuminate\Support\Facades\DB;

final class DatabaseFloorPlan implements FloorPlan
{
    public function sync(Table $table): void
    {
        DB::table(Tables::FLOOR_PLAN)->where('table_id', (string) $table->id())->update([
            'label' => (string) $table->label(),
            'guest_count' => $table->guestCount(),
            'status' => $table->status(),
        ]);
    }
}
