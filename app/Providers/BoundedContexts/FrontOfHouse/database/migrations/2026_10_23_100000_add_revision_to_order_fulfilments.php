<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foh_order_fulfilments', function (Blueprint $table) {
            $table->json('unavailable_lines')->nullable();
            $table->unsignedBigInteger('request_position')->default(0);
            $table->index(['table_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('foh_order_fulfilments', function (Blueprint $table) {
            $table->dropIndex(['table_id', 'status']);
            $table->dropColumn(['unavailable_lines', 'request_position']);
        });
    }
};
