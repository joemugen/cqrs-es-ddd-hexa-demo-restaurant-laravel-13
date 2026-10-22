<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foh_tables', function (Blueprint $table) {
            $table->string('visit_id')->nullable()->index();
        });

        Schema::table('foh_table_orders', function (Blueprint $table) {
            $table->string('visit_id')->nullable()->index();
        });

        Schema::table('foh_order_fulfilments', function (Blueprint $table) {
            $table->string('visit_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        foreach (['foh_tables', 'foh_table_orders', 'foh_order_fulfilments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['visit_id']);
                $table->dropColumn('visit_id');
            });
        }
    }
};
