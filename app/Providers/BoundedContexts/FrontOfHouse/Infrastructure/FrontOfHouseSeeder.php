<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class FrontOfHouseSeeder extends Seeder
{
    public function run(): void
    {
        $tables = [
            ['id' => 'table-4', 'label' => 'Window 4'],
            ['id' => 'table-7', 'label' => 'Terrace 7'],
        ];

        foreach ($tables as $table) {
            DB::table('foh_tables')->insert([
                'id' => $table['id'],
                'label' => $table['label'],
                'guest_count' => 0,
            ]);

            DB::table('foh_floor_plan')->insert([
                'table_id' => $table['id'],
                'label' => $table['label'],
                'guest_count' => 0,
                'status' => 'free',
            ]);
        }
    }
}
