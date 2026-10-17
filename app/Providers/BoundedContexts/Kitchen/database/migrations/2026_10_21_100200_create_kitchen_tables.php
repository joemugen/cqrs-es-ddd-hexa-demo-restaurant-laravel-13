<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_dishes', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->unique();
            $table->string('station');
            $table->unsignedInteger('cooking_minutes');
            $table->boolean('in_stock')->default(true);
        });

        Schema::create('kitchen_screen_tickets', function (Blueprint $table) {
            $table->string('ticket_id')->primary();
            $table->string('party_ref');
            $table->string('table_label');
            $table->boolean('done')->default(false);
        });

        Schema::create('kitchen_screen_lines', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id');
            $table->string('line_id');
            $table->string('dish_name');
            $table->string('station');
            $table->unsignedInteger('cooking_minutes');
            $table->unsignedInteger('quantity');
            $table->string('course');
            $table->json('allergens');
            $table->string('status');
            $table->unique(['ticket_id', 'line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_screen_lines');
        Schema::dropIfExists('kitchen_screen_tickets');
        Schema::dropIfExists('kitchen_dishes');
    }
};
