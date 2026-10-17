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
        DB::table('foh_floor_plan')->where('table_id', $table->id())->update([
            'label' => $table->label(),
            'guest_count' => $table->guestCount(),
            'status' => $table->status(),
        ]);
    }
}
