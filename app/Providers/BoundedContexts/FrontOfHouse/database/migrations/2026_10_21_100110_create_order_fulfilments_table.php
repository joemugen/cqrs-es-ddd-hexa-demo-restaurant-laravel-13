<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foh_order_fulfilments', function (Blueprint $table) {
            $table->string('table_order_id')->primary();
            $table->string('table_id');
            $table->string('status');
            $table->text('reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foh_order_fulfilments');
    }
};
