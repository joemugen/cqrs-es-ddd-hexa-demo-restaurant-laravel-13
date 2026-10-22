<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The database holds that a table has one open visit at a time: a partial unique index on the visits that are not closed.
 * Two seatings that read the same free table cannot both be written, with any database that has partial indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foh_visits', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('table_id');
            $table->timestamp('closed_at')->nullable();
        });

        DB::statement('create unique index foh_visits_one_open_per_table on foh_visits (table_id) where closed_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('foh_visits');
    }
};
