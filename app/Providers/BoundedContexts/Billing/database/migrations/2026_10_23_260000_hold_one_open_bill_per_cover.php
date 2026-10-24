<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The database holds that a cover has one open bill at a time: a partial unique index on the bills that are open. Two
 * requests for the bill of the same visit cannot both create one, whatever the order in which they read.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("create unique index billing_bills_one_open_per_cover on billing_bills (cover_ref) where status = 'open'");
    }

    public function down(): void
    {
        DB::statement('drop index billing_bills_one_open_per_cover');
    }
};
