<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('foh_to_serve');

        Schema::create('foh_pass', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('ticket_id');
            $table->string('line_id');
            $table->string('table_id');
            $table->string('dish_name');
            $table->unsignedInteger('quantity');
            $table->timestamp('served_at')->nullable();
            $table->unique(['ticket_id', 'line_id']);
        });

        Schema::create('foh_to_serve', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('position')->index();
            $table->string('ticket_id');
            $table->string('line_id');
            $table->string('table_id');
            $table->string('table_label');
            $table->string('dish_name');
            $table->unsignedInteger('quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foh_to_serve');
        Schema::dropIfExists('foh_pass');

        Schema::create('foh_to_serve', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id');
            $table->string('line_id');
            $table->string('table_id');
            $table->string('table_label');
            $table->string('dish_name');
            $table->unsignedInteger('quantity');
            $table->timestamp('served_at')->nullable();
            $table->unique(['ticket_id', 'line_id']);
        });
    }
};
