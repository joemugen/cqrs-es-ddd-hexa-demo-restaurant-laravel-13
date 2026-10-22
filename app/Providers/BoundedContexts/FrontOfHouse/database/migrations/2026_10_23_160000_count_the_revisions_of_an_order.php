<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foh_table_orders', function (Blueprint $table) {
            $table->unsignedInteger('revisions')->default(0);
        });

        Schema::table('foh_order_fulfilments', function (Blueprint $table) {
            $table->unsignedInteger('revisions_seen')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('foh_table_orders', function (Blueprint $table) {
            $table->dropColumn('revisions');
        });

        Schema::table('foh_order_fulfilments', function (Blueprint $table) {
            $table->dropColumn('revisions_seen');
        });
    }
};
